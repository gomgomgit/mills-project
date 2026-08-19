import { query, run } from '@/services/localDb'

/**
 * weighbridgeRecordRepo — screen-007--monitor-weighbridge /
 * usecase-007--monitor-weighbridge business_logic steps 1-5. Local (offline)
 * read/write access to the `weighbridge_record` table (schema defined in
 * localSchema.ts) — mirrors draftRecordsRepo.ts's/stationRepo.ts's repo
 * style (plain async functions over localDb.ts's `query`/`run`
 * primitives), but this is the first repo in the app that also WRITES
 * (INSERT/UPDATE/DELETE), not just SELECT.
 *
 * Update (screen-010--form-weighbridge): adds `getDraftById()` (SELECT one
 * row by id, for Form Weighbridge to load a draft on mount) and
 * `saveDraft()` (business_logic steps 3-5 — UPDATEs the row with the user's
 * form input, status='saved', updated_at=now). `saveDraft()` is also where
 * business_logic step 4's role-based `checked_by` stripping is enforced —
 * deliberately at this repo level (not only in FormWeighbridgeView.vue's UI)
 * so the rule holds even if a caller bypasses the UI.
 *
 * Update (screen-007--monitor-weighbridge, list-view rewrite): adds
 * `getDrafts()` — business_logic step 1 of the rewritten Monitor
 * Weighbridge screen, which now shows a full scrollable list of the
 * current user's ongoing/paused drafts (every row labeled uniformly
 * "Pause" in the UI, no ongoing-vs-paused distinction) instead of a single
 * current-draft summary. `getSummary()` and `resumeDraft()` are left in
 * place unchanged (`getSummary()` is still used by StationListView.vue's
 * draft-status-by-type lookup; `resumeDraft()` is no longer called by this
 * screen going forward but is kept for parity with
 * gradingRecordRepo.ts/cagesTrackRecordRepo.ts's still-active use of their
 * own equivalents, and in case another caller needs it later).
 *
 * Update (screen-010--form-weighbridge major rewrite): adds
 * `pauseDraftWithFormData()` — Form Weighbridge's new 'Pause' footer
 * action needs to persist the user's current in-progress field values (a
 * checkpoint save, unlike the existing `pauseDraft()` above which only
 * flips `status` and touches no other column) while still landing on
 * status='draft_paused'. Deliberately a new function rather than an
 * extension of `pauseDraft()`: `pauseDraft()`'s no-op-when-not-ongoing
 * guard (`WHERE status = 'draft_ongoing'`) is load-bearing for its own
 * caller contract (see its own doc comment / test scenario "Pause Ditekan
 * Tanpa Draft Ongoing"), and Form Weighbridge must be able to re-pause an
 * already-paused draft (edit → Pause again) without that guard blocking
 * the write. `pauseDraft()`/`resumeDraft()` are otherwise untouched.
 *
 * Update (screen-013--data-preview-weighbridge, list-view rewrite): adds
 * `getAllRecords()` — Data Preview Weighbridge's new LIST mode
 * (business_logic step 1) needs every local `weighbridge_record` row for
 * the current user across ALL statuses (draft_ongoing/draft_paused/
 * saved/synced), unlike `getDrafts()` above which intentionally filters
 * to only draft_ongoing/draft_paused for Monitor Weighbridge's own list.
 * Purely additive — `getDrafts()`, `getSummary()`, and every other
 * exported function above are unchanged.
 *
 * Update (screen-007--monitor-weighbridge, "today's counter" addition):
 * adds `getTodaySummary()` — Monitor Weighbridge's new "Hari Ini" section
 * needs a count + sums scoped to TODAY only (local device date), unlike
 * `getSummary()` above which is an all-time running tally. Deliberately a
 * separate function rather than an extra param on `getSummary()`: the two
 * have different WHERE-scoping (all-time vs. today) and different return
 * shapes (`getSummary()` also carries `currentDraft`, which the counter
 * section does not need), so keeping them independent avoids overloading
 * `getSummary()`'s existing callers/contract. No status filter — every
 * status (draft_ongoing/draft_paused/saved/synced) counts, matching
 * `getSummary()`'s own no-status-filter behavior. Purely additive — every
 * other exported function above (including `getSummary()`) is unchanged.
 */

export type WeighbridgeDraftStatus = 'draft_ongoing' | 'draft_paused'
export type WeighbridgeRecordStatus = WeighbridgeDraftStatus | 'saved' | 'synced'

export interface CurrentDraft {
  id: string
  status: WeighbridgeDraftStatus
}

/**
 * screen-010--form-weighbridge — full local `weighbridge_record` row shape
 * (mirrors localSchema.ts's CREATE_WEIGHBRIDGE_RECORD column-for-column).
 * `getDraftById()`/`saveDraft()` below operate on this shape.
 */
export interface WeighbridgeRecord {
  id: string
  station_id: string | null
  wb_card_number: string | null
  arrival_datetime: string | null
  dispatch_datetime: string | null
  vehicle_number: string | null
  driver_name: string | null
  estate_supplier: string | null
  division: string | null
  block: string | null
  gross_weight: number | null
  tare_weight: number | null
  net_weight: number | null
  quantity: number | null
  checked_by: string | null
  acknowledged_by: string | null
  status: WeighbridgeRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

/**
 * screen-010--form-weighbridge business_logic step 3 — the subset of
 * `WeighbridgeRecord` columns the Form Weighbridge screen actually collects
 * from the user (excludes id/station_id/created_by/created_at/updated_at/
 * status, which are managed by createDraft()/resumeDraft()/saveDraft()
 * itself, not user-entered).
 */
export interface WeighbridgeFormData {
  wb_card_number: string
  arrival_datetime: string
  dispatch_datetime: string
  vehicle_number: string
  driver_name: string
  estate_supplier: string
  division: string
  block: string
  gross_weight: number | null
  tare_weight: number | null
  net_weight: number | null
  quantity: number | null
  checked_by: string
  acknowledged_by: string
}

/**
 * User roles a `saveDraft()` caller may pass as `currentUserRole` — kept as
 * a plain string union (not imported from stores/auth.ts's
 * `AuthUser['role']`) so this repo stays decoupled from the auth store,
 * matching the rest of this file's style (no cross-import of app state into
 * the repo layer). Callers pass `authStore.currentUser?.role` straight
 * through.
 */
export type WeighbridgeActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

interface SummaryAggregateRow {
  sum_wb_card: number
  sum_net_weight: number | null
  sum_quantity: number | null
}

interface CurrentDraftRow {
  id: string
  status: WeighbridgeDraftStatus
}

/**
 * screen-007--monitor-weighbridge "today's counter" addition — one row of
 * `getTodaySummary()`'s SQL aggregate (see that function's doc comment).
 */
export interface WeighbridgeTodaySummary {
  countWb: number
  sumNetWeight: number
  sumQuantity: number
}

interface TodaySummaryAggregateRow {
  count_wb: number
  sum_net_weight: number | null
  sum_quantity: number | null
}

/**
 * screen-007--monitor-weighbridge (list-view rewrite) business_logic step
 * 1 — one row of the current user's ongoing/paused draft list.
 * `wb_card_number` is included (unlike `CurrentDraft`/`CurrentDraftRow`
 * above) so the list can render each draft's WB Card number, falling back
 * to a placeholder in the view when null/empty.
 */
export interface WeighbridgeDraftListItem {
  id: string
  status: WeighbridgeDraftStatus
  wb_card_number: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: WeighbridgeDraftStatus
  wb_card_number: string | null
  updated_at: string
}

/**
 * business_logic step 1 — Sum WB Card (record count), Sum Net Weight, Sum
 * Quantity for the current user, plus their current draft (ongoing or
 * paused, if any) so the UI can render a status badge.
 */
export interface WeighbridgeSummary {
  sumWbCard: number
  sumNetWeight: number
  sumQuantity: number
  currentDraft: CurrentDraft | null
}

const DRAFT_STATUSES: WeighbridgeDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

/**
 * Generates a local primary-key id for a new `weighbridge_record` row.
 * `crypto.randomUUID()` is available in both the target runtimes for this
 * app (modern WebView / Node 19+, matching this repo's Vitest/jsdom test
 * environment) — a manual fallback is kept only for older embedded
 * WebViews that may lack it, consistent with an offline-first mobile app
 * not being able to assume the newest platform APIs everywhere.
 */
function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `wb-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

/**
 * business_logic step 1 — aggregate summary (count + sums) across every
 * local weighbridge_record for the current user, regardless of status
 * (running tally, not scoped to drafts only), plus the current
 * ongoing/paused draft (if any) for the status badge. Computed with SQL
 * aggregate functions rather than in JS so it stays a single round trip
 * per query, matching the rest of this app's local-repo style.
 */
export async function getSummary(userId: string): Promise<WeighbridgeSummary> {
  const [aggregateRows, draftRows] = await Promise.all([
    query<SummaryAggregateRow>(
      `SELECT
         COUNT(*) AS sum_wb_card,
         COALESCE(SUM(net_weight), 0) AS sum_net_weight,
         COALESCE(SUM(quantity), 0) AS sum_quantity
       FROM weighbridge_record
       WHERE created_by = ?`,
      [userId],
    ),
    query<CurrentDraftRow>(
      `SELECT id, status
       FROM weighbridge_record
       WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
       ORDER BY updated_at DESC
       LIMIT 1`,
      [userId],
    ),
  ])

  const aggregate = aggregateRows[0]
  const draftRow = draftRows[0]

  return {
    sumWbCard: aggregate?.sum_wb_card ?? 0,
    sumNetWeight: aggregate?.sum_net_weight ?? 0,
    sumQuantity: aggregate?.sum_quantity ?? 0,
    currentDraft: draftRow ? { id: draftRow.id, status: draftRow.status } : null,
  }
}

/**
 * screen-007--monitor-weighbridge "today's counter" addition — count +
 * sums across every local weighbridge_record for the current user whose
 * `arrival_datetime` falls on today's local (device) date, regardless of
 * status (no status filter, same as `getSummary()`). Computed with a
 * single SQL aggregate query, matching this repo's existing
 * `getSummary()` style. `date('now', 'localtime')` (rather than plain
 * `date('now')`, which is UTC) is used deliberately so "today" matches the
 * device's local day boundary, not UTC's.
 */
export async function getTodaySummary(userId: string): Promise<WeighbridgeTodaySummary> {
  const rows = await query<TodaySummaryAggregateRow>(
    `SELECT
       COUNT(*) AS count_wb,
       COALESCE(SUM(net_weight), 0) AS sum_net_weight,
       COALESCE(SUM(quantity), 0) AS sum_quantity
     FROM weighbridge_record
     WHERE created_by = ? AND date(arrival_datetime) = date('now', 'localtime')`,
    [userId],
  )

  const aggregate = rows[0]

  return {
    countWb: aggregate?.count_wb ?? 0,
    sumNetWeight: aggregate?.sum_net_weight ?? 0,
    sumQuantity: aggregate?.sum_quantity ?? 0,
  }
}

/**
 * screen-007--monitor-weighbridge (list-view rewrite) business_logic step
 * 1 — every local `weighbridge_record` the current user has ongoing or
 * paused, most-recently-updated first. Every returned row is labeled
 * uniformly "Pause" by the caller (MonitorWeighbridgeView.vue) — the
 * ongoing/paused distinction is carried in `status` purely for
 * completeness/future use, not surfaced as separate UI labels.
 */
export async function getDrafts(userId: string): Promise<WeighbridgeDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, wb_card_number, updated_at
     FROM weighbridge_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    wb_card_number: row.wb_card_number,
    updated_at: row.updated_at,
  }))
}

/**
 * screen-013--data-preview-weighbridge (list-view) business_logic step 1
 * — every local `weighbridge_record` row for the current user, regardless
 * of status, most-recently-updated first. Returns the full row shape
 * (`WeighbridgeRecord`, same as `getDraftById()`'s single row) since this
 * screen's DETAIL mode also needs every field, not just the list-summary
 * subset `getDrafts()`/`WeighbridgeDraftListItem` returns. Date/search
 * filtering is done client-side by the caller (in-memory, on this
 * already-fetched array) — this function never re-queries per filter
 * change.
 */
export async function getAllRecords(userId: string): Promise<WeighbridgeRecord[]> {
  return query<WeighbridgeRecord>(
    `SELECT * FROM weighbridge_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * business_logic step 2 — 'Mulai Input Baru'. INSERTs a new
 * weighbridge_record row with status='draft_ongoing', created_by=current
 * user, a generated id, and audit timestamps. Returns the new record's id
 * so the caller can navigate to Form Weighbridge with it.
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO weighbridge_record (id, status, created_by, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?)`,
    [id, userId, timestamp, timestamp],
  )

  return id
}

/**
 * business_logic step 3 — 'Lanjutkan draft paused'. UPDATEs the given
 * record's status back to 'draft_ongoing' (and updated_at). Returns the
 * updated record's id/status so the caller can navigate to Form
 * Weighbridge with it.
 */
export async function resumeDraft(recordId: string): Promise<CurrentDraft> {
  const timestamp = nowIso()

  await run(`UPDATE weighbridge_record SET status = 'draft_ongoing', updated_at = ? WHERE id = ?`, [
    timestamp,
    recordId,
  ])

  return { id: recordId, status: 'draft_ongoing' }
}

/**
 * business_logic step 4 — 'Pause'. UPDATEs the current ongoing record's
 * status to 'draft_paused' (and updated_at).
 *
 * Guard (per business rule + test scenario "Pause Ditekan Tanpa Draft
 * Ongoing"): when there is no ongoing draft to pause, this is a no-op —
 * the underlying `run()` write primitive must NOT be invoked at all. The
 * caller (MonitorWeighbridgeView.vue) only ever has an id to pass when
 * `currentDraft.status === 'draft_ongoing'` (see the view's own disabled-
 * button guard), so `recordId` being falsy IS the "no ongoing draft"
 * signal here — checked and early-returned before any DB call, so a test
 * spying on `run` can assert it was never called.
 */
export async function pauseDraft(recordId: string | null | undefined): Promise<void> {
  if (!recordId) {
    return
  }

  const timestamp = nowIso()

  await run(
    `UPDATE weighbridge_record SET status = 'draft_paused', updated_at = ? WHERE id = ? AND status = 'draft_ongoing'`,
    [timestamp, recordId],
  )
}

/**
 * business_logic step 5 — 'Clear' (after UI-level confirm). Permanently
 * DELETEs the record. Confirmation itself is a UI concern
 * (ConfirmDialog.vue / MonitorWeighbridgeView.vue) — this function deletes
 * unconditionally once called; the cancel path simply never calls it.
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM weighbridge_record WHERE id = ?`, [recordId])
}

/**
 * screen-010--form-weighbridge business_logic step 1 — Form Weighbridge
 * receives a draft `id` via route param and loads that existing row (a
 * draft already INSERTed by Monitor Weighbridge's 'Mulai Input Baru' /
 * 'Lanjutkan'). Returns `null` when no row matches (defensive — e.g. a
 * stale/invalid route param), letting the caller show a "not found" state
 * instead of throwing.
 */
export async function getDraftById(recordId: string): Promise<WeighbridgeRecord | null> {
  const rows = await query<WeighbridgeRecord>(`SELECT * FROM weighbridge_record WHERE id = ?`, [recordId])

  return rows[0] ?? null
}

/**
 * screen-010--form-weighbridge business_logic steps 4-5 — 'Simpan'.
 * UPDATEs every form field on the given record, then sets
 * status='saved'/updated_at=now. Required-field validation (business_logic
 * step 3) is the caller's (FormWeighbridgeView.vue's) job — this function
 * assumes it has already been validated — but the `checked_by`
 * role-stripping rule (business_logic step 4) is enforced HERE, not only in
 * the UI, so it holds even if a caller bypasses the form: whenever
 * `currentUserRole !== 'supervisor'`, whatever value is present in
 * `formData.checked_by` is ignored and `null` is written instead.
 *
 * Returns the freshly saved row (re-read via getDraftById()) so the caller
 * can rely on the persisted state rather than re-deriving it locally.
 */
export async function saveDraft(
  recordId: string,
  formData: WeighbridgeFormData,
  currentUserRole: WeighbridgeActorRole | null | undefined,
): Promise<WeighbridgeRecord | null> {
  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && formData.checked_by ? formData.checked_by : null

  await run(
    `UPDATE weighbridge_record
     SET wb_card_number = ?,
         arrival_datetime = ?,
         dispatch_datetime = ?,
         vehicle_number = ?,
         driver_name = ?,
         estate_supplier = ?,
         division = ?,
         block = ?,
         gross_weight = ?,
         tare_weight = ?,
         net_weight = ?,
         quantity = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'saved',
         updated_at = ?
     WHERE id = ?`,
    [
      formData.wb_card_number || null,
      formData.arrival_datetime || null,
      formData.dispatch_datetime || null,
      formData.vehicle_number || null,
      formData.driver_name || null,
      formData.estate_supplier || null,
      formData.division || null,
      formData.block || null,
      formData.gross_weight,
      formData.tare_weight,
      formData.net_weight,
      formData.quantity,
      checkedBy,
      formData.acknowledged_by || null,
      timestamp,
      recordId,
    ],
  )

  return getDraftById(recordId)
}

/**
 * screen-010--form-weighbridge business_logic step 6 — 'Pause'. UPDATEs
 * every form field on the given record (same column set as saveDraft(),
 * including `checked_by`/`acknowledged_by` for shape compatibility with
 * WeighbridgeFormData — Form Weighbridge no longer collects either field
 * itself and always sends empty strings for them, which round-trip as
 * `null` here exactly like saveDraft()), then sets
 * status='draft_paused'/updated_at=now.
 *
 * Deliberately NOT gated behind required-field validation (Pause is a
 * checkpoint save, not a final one — that's the caller's/UI's concern, not
 * this function's) and has NO status WHERE-guard (unlike `pauseDraft()`
 * above) — it must succeed whether the record is currently
 * 'draft_ongoing' (first pause) or already 'draft_paused' (re-pausing an
 * already-paused draft after further edits), so field values are never
 * silently dropped.
 *
 * Returns the freshly paused row (re-read via getDraftById()), same
 * pattern as saveDraft().
 */
export async function pauseDraftWithFormData(
  recordId: string,
  formData: WeighbridgeFormData,
): Promise<WeighbridgeRecord | null> {
  const timestamp = nowIso()

  await run(
    `UPDATE weighbridge_record
     SET wb_card_number = ?,
         arrival_datetime = ?,
         dispatch_datetime = ?,
         vehicle_number = ?,
         driver_name = ?,
         estate_supplier = ?,
         division = ?,
         block = ?,
         gross_weight = ?,
         tare_weight = ?,
         net_weight = ?,
         quantity = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'draft_paused',
         updated_at = ?
     WHERE id = ?`,
    [
      formData.wb_card_number || null,
      formData.arrival_datetime || null,
      formData.dispatch_datetime || null,
      formData.vehicle_number || null,
      formData.driver_name || null,
      formData.estate_supplier || null,
      formData.division || null,
      formData.block || null,
      formData.gross_weight,
      formData.tare_weight,
      formData.net_weight,
      formData.quantity,
      formData.checked_by || null,
      formData.acknowledged_by || null,
      timestamp,
      recordId,
    ],
  )

  return getDraftById(recordId)
}

export const weighbridgeRecordRepo = {
  getSummary,
  getTodaySummary,
  getDrafts,
  getAllRecords,
  createDraft,
  resumeDraft,
  pauseDraft,
  pauseDraftWithFormData,
  deleteDraft,
  getDraftById,
  saveDraft,
}

export default weighbridgeRecordRepo

// Re-exported for readability at call sites that only need the status enum.
export const WEIGHBRIDGE_DRAFT_STATUSES = DRAFT_STATUSES
