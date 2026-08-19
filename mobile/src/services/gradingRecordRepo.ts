import { query, run } from '@/services/localDb'

/**
 * gradingRecordRepo — screen-008--monitor-grading /
 * usecase-008--monitor-grading business_logic steps 1-5. Local (offline)
 * read/write access to the `grading_record` table (schema defined in
 * localSchema.ts) — mirrors weighbridgeRecordRepo.ts's repo style (plain
 * async functions over localDb.ts's `query`/`run` primitives) as closely
 * as sensible given grading's different business logic.
 *
 * Difference from weighbridgeRecordRepo.ts: Monitor Grading's progress
 * summary (business_logic step 1) is a single "jumlah record dinilai pada
 * sesi berjalan" count, not multiple numeric sums (weighbridge's
 * sumWbCard/sumNetWeight/sumQuantity) — grading_record has no
 * weight/quantity columns of its own (those live per-row on
 * `grading_detail`, owned by screen-011--form-grading, out of this
 * screen's scope). `deleteDraft()` also additionally cascades the delete
 * to `grading_detail` rows (weighbridge_record has no child table to
 * cascade).
 *
 * Update (screen-011--form-grading): adds `getDraftWithDetails()` (SELECT
 * the `grading_record` header row plus all of its `grading_detail` child
 * rows, for Form Grading to load a draft on mount — mirrors
 * weighbridgeRecordRepo.ts's `getDraftById()`, extended with the child
 * rows this screen also owns) and `saveDraft()` (business_logic steps 4-7
 * — validates at least one grading_detail row exists, UPDATEs the header
 * with status='saved', and upserts every detail row: rows with an
 * existing `id` are UPDATEd, rows without one are INSERTed with a freshly
 * generated id). `saveDraft()` is also where business_logic step 6's
 * role-based `checked_by` stripping is enforced — deliberately at this
 * repo level (not only in FormGradingView.vue's UI), same reasoning as
 * weighbridgeRecordRepo.ts's `saveDraft()`, so the rule holds even if a
 * caller bypasses the UI.
 *
 * Transaction handling: `localDb.ts` exposes only `query()`/`run()` — no
 * transaction primitive (see its header comment; still true as of this
 * screen). `saveDraft()` therefore performs the header UPDATE followed by
 * each detail row's UPDATE/INSERT as sequential `run()` calls (header
 * first, then details in array order) rather than a single atomic
 * transaction — same documented limitation as this file's own
 * `deleteDraft()` and weighbridgeRecordRepo.ts's `saveDraft()`. A
 * mid-sequence failure can leave the header saved with only a subset of
 * detail rows persisted. Acceptable for this stage (single-user local
 * device, no concurrent writers) but noted here for anyone adding a real
 * transaction primitive to localDb.ts later.
 *
 * Update (screen-008--monitor-grading, list-view + entity-catalog v2
 * rewrite): adds `getDrafts()` — business_logic step 2 of the rewritten
 * Monitor Grading screen, which now shows a full scrollable list of the
 * current user's ongoing/paused drafts (every row labeled uniformly
 * "Pause" in the UI, no ongoing-vs-paused distinction), mirroring
 * weighbridgeRecordRepo.ts's `getDrafts()` (screen-007's own equivalent
 * list-view rewrite). Also adds `getTodaySummary()` — the screen's new
 * "Hari Ini" 3-card counter section (business_logic step 1: COUNT + SUM
 * of `netto`/`quantity` scoped to today's local device date via
 * `date(date) = date('now','localtime')`), mirroring
 * weighbridgeRecordRepo.ts's own `getTodaySummary()` "today's counter"
 * addition, adapted to entity-catalog v2's `grading_record` columns
 * (`date`, `netto`, `quantity` — replacing the pre-v2 schema this file's
 * older `GradingRecord`/`saveDraft()`/`getDraftWithDetails()` block above
 * still assumes; that block belongs to screen-011--form-grading, not yet
 * revised for entity-catalog v2, and is deliberately left untouched here —
 * see this screen's implementation_notes). `getProgressSummary()` is left
 * in place unchanged (still used by StationListView.vue's
 * draft-status-by-type lookup); `resumeDraft()`/`pauseDraft()`/
 * `deleteDraft()` are no longer called by this screen going forward but
 * are kept for parity with weighbridgeRecordRepo.ts's own equivalents,
 * and in case another caller needs them later. Purely additive — every
 * other exported function/type in this file (including the entire
 * screen-011--form-grading block below) is unchanged.
 *
 * Update (screen-011--form-grading, entity-catalog v2 rewrite,
 * 2026-08-19): the screen-011--form-grading block below (previously a
 * placeholder built against grading_record/grading_detail's PRE-v2
 * columns — vehicle_number/driver_name/block on the header, free-text
 * `category` on the detail row — that never actually matched
 * localSchema.ts's authoritative v2 shape) is now fully rewritten to
 * match localSchema.ts's CREATE_GRADING_RECORD / CREATE_GRADING_DETAIL /
 * CREATE_GRADING_PARAMETER column-for-column:
 *   - `GradingRecord` gains weighbridge_record_id/license_plate_no/
 *     vehicle_code/netto/quantity/note, loses vehicle_number/driver_name/
 *     block.
 *   - `GradingDetailRow` gains grading_parameter_id/uom/percentage, loses
 *     `category`.
 *   - New `getWeighbridgeRecordOptions()` (every local weighbridge_record
 *     row, any status, ordered by arrival_datetime DESC — deliberately
 *     NOT scoped to `created_by`, unlike every other reader in this file;
 *     this is the WB Card No dropdown's reference list, and a grading
 *     record may legitimately reference a weighbridge_record created by a
 *     different device user, per this screen's tech spec) and
 *     `getGradingParameterOptions()` (every grading_parameter row,
 *     ordered by sort_order — the master list is global/unscoped, same
 *     reasoning as localSchema.ts's own unconditional seed) are added for
 *     FormGradingView.vue's two reference dropdowns.
 *   - `saveDraft()`/new `pauseDraftWithFormData()` both take an explicit
 *     `detailIdsToDelete: string[]` param (rows the user removed via
 *     "Hapus baris" — FormGradingView.vue queues these rather than
 *     deleting immediately) in addition to the upserted `detailRows`
 *     array, and neither touches `checked_by`/`acknowledged_by` at all:
 *     per this rewrite's tech spec, Form Grading's field sections no
 *     longer include a Checked By/Acknowledged By section at all (mirrors
 *     FormWeighbridgeView.vue's 2026-08-18 "MAJOR REWRITE" removing
 *     Checked By from that screen entirely) — those two columns are
 *     simply left at whatever `createDraft()` (screen-008 block above)
 *     already set them to (NULL, since that INSERT never populates
 *     them), rather than being explicitly re-written to NULL on every
 *     save/pause. `GradingActorRole` is kept (exported, unused by
 *     `saveDraft()`/`pauseDraftWithFormData()` now that there is no
 *     role-gated field) purely for parity with
 *     weighbridgeRecordRepo.ts's `WeighbridgeActorRole` / possible future
 *     callers.
 *   - `saveDraft()`'s required-detail-row gate now counts a row as
 *     "valid" only when it has both `grading_parameter_id` AND a
 *     non-null/non-undefined `quantity` (mirrors this screen's tech
 *     spec's "Quality Parameter selected + Qty filled"); throws
 *     `GradingDetailRequiredError` (same class, same message) when zero
 *     rows qualify — before ANY write happens, same as before. Every row
 *     the caller passes (as long as it hasn't been queued for deletion)
 *     is still upserted as-is once that gate passes — this function does
 *     not itself decide which individual rows are "complete enough" to
 *     persist beyond that one gate; FormGradingView is not expected to
 *     send fully-blank rows in the first place (a freshly-added, still-
 *     untouched "Tambah baris" row has no id and no `grading_parameter_id`,
 *     so an INSERT for it is harmless — it simply never happens unless
 *     the user actually engages with that row before Simpan/Pause is
 *     pressed, which then makes it a legitimate row).
 *   - `pauseDraftWithFormData()` mirrors `saveDraft()`'s upsert/delete
 *     semantics for detail rows exactly, but has NO validation gate at
 *     all (status='draft_paused', a checkpoint save) — same
 *     validate-vs-no-validate split as weighbridgeRecordRepo.ts's
 *     `saveDraft()` vs `pauseDraftWithFormData()`.
 *   - Explicit "Clear" delete is NOT reimplemented here — the screen-008
 *     block's `deleteDraft()` above already does exactly what Clear needs
 *     (delete every `grading_detail` row for the record, then the
 *     `grading_record` row itself) and is left untouched/exported;
 *     FormGradingView.vue calls that shared function directly rather than
 *     this file gaining a second, duplicate `clearDraft()`.
 *   - Id generation for new detail rows: unchanged, still
 *     `generateDetailId()` (crypto.randomUUID() with a manual fallback),
 *     same approach as `generateId()` above / weighbridgeRecordRepo.ts's
 *     own `generateId()`.
 *   - Transaction handling: unchanged — see this file's own "Transaction
 *     handling" paragraph above; sequential `run()` calls, no single
 *     atomic transaction.
 *
 * Update (screen-014--data-preview-grading, list-view rewrite): adds
 * `getAllRecords()` — Data Preview Grading's new LIST mode
 * (business_logic step 1) needs every local `grading_record` row for the
 * current user across ALL statuses (draft_ongoing/draft_paused/saved/
 * synced), unlike `getDrafts()` above which intentionally filters to only
 * draft_ongoing/draft_paused for Monitor Grading's own list. Mirrors
 * weighbridgeRecordRepo.ts's own `getAllRecords()`
 * (screen-013--data-preview-weighbridge) column-for-column, adapted to
 * `grading_record`'s entity-catalog v2 shape (`GradingRecord`, the same
 * full-row type `getDraftWithDetails()` already uses). DETAIL mode
 * continues to reuse `getDraftWithDetails()` unchanged — this function is
 * for the LIST-mode summary array only, it does not touch
 * `grading_detail` at all. Purely additive — every other exported
 * function in this file (including the entire screen-011--form-grading
 * block above) is unchanged.
 */

export type GradingDraftStatus = 'draft_ongoing' | 'draft_paused'
export type GradingRecordStatus = GradingDraftStatus | 'saved' | 'synced'

export interface CurrentDraft {
  id: string
  status: GradingDraftStatus
}

/**
 * screen-008--monitor-grading "today's counter" addition — one row of
 * `getTodaySummary()`'s SQL aggregate (see that function's doc comment).
 */
export interface GradingTodaySummary {
  countGrading: number
  sumNetto: number
  sumQuantity: number
}

interface TodaySummaryAggregateRow {
  count_grading: number
  sum_netto: number | null
  sum_quantity: number | null
}

/**
 * screen-008--monitor-grading (list-view rewrite) business_logic step 2 —
 * one row of the current user's ongoing/paused draft list.
 * `grading_number` is included (unlike `CurrentDraft`/`CurrentDraftRow`
 * below) so the list can render each draft's Grading Number, falling back
 * to a placeholder in the view when null/empty — mirrors
 * weighbridgeRecordRepo.ts's `WeighbridgeDraftListItem`/`wb_card_number`.
 */
export interface GradingDraftListItem {
  id: string
  status: GradingDraftStatus
  grading_number: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: GradingDraftStatus
  grading_number: string | null
  updated_at: string
}

/**
 * screen-011--form-grading (entity-catalog v2) — full local
 * `grading_record` row shape (mirrors localSchema.ts's
 * CREATE_GRADING_RECORD column-for-column). `getDraftWithDetails()`/
 * `saveDraft()`/`pauseDraftWithFormData()` below operate on this shape.
 */
export interface GradingRecord {
  id: string
  station_id: string | null
  grading_number: string | null
  date: string | null
  weighbridge_record_id: string | null
  license_plate_no: string | null
  vehicle_code: string | null
  estate_supplier: string | null
  division: string | null
  netto: number | null
  quantity: number | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  status: GradingRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

/**
 * screen-011--form-grading (entity-catalog v2) — full local
 * `grading_detail` row shape (mirrors localSchema.ts's
 * CREATE_GRADING_DETAIL column-for-column). `uom` is a snapshot copied
 * from the selected `grading_parameter.uom` at selection time (not a live
 * join — see CREATE_GRADING_DETAIL's own comment in localSchema.ts).
 * `percentage` is computed client-side by FormGradingView.vue
 * (qty/header.netto*100 when uom='kg', qty/header.quantity*100 when
 * uom='bunch') and persisted as-is by `saveDraft()`/
 * `pauseDraftWithFormData()` — this repo never recomputes it.
 */
export interface GradingDetailRow {
  id: string
  grading_record_id: string
  grading_parameter_id: string | null
  quantity: number | null
  uom: string | null
  percentage: number | null
  created_at: string
  updated_at: string
}

/**
 * screen-011--form-grading — read-only reference row for the "Quality
 * Parameter" dropdown (mirrors localSchema.ts's CREATE_GRADING_PARAMETER
 * column-for-column, minus the audit timestamps FormGradingView.vue never
 * needs). See `getGradingParameterOptions()` below.
 */
export interface GradingParameterOption {
  id: string
  name: string
  uom: string
  sort_order: number
}

/**
 * screen-011--form-grading — read-only reference row for the "WB Card No"
 * dropdown (a subset of `weighbridge_record`'s columns — this screen only
 * ever reads/displays these five). See `getWeighbridgeRecordOptions()`
 * below.
 */
export interface WeighbridgeRecordOption {
  id: string
  wb_card_number: string | null
  arrival_datetime: string | null
  vehicle_number: string | null
  estate_supplier: string | null
  division: string | null
}

/**
 * screen-011--form-grading business_logic step 1 — a `grading_record`
 * header row plus all of its `grading_detail` child rows, as loaded by
 * `getDraftWithDetails()` and rendered by FormGradingView.vue.
 */
export interface GradingDraftWithDetails {
  record: GradingRecord
  details: GradingDetailRow[]
}

/**
 * screen-011--form-grading business_logic step 4 — the subset of
 * `GradingRecord` columns the Form Grading screen actually collects from
 * the user (excludes id/station_id/created_by/created_at/updated_at/
 * status/checked_by/acknowledged_by — the last two are managed by nothing
 * on this screen at all, see this file's "entity-catalog v2 rewrite"
 * update note above: Form Grading has no Checked By/Acknowledged By
 * section).
 */
export interface GradingHeaderFormData {
  grading_number: string
  date: string
  weighbridge_record_id: string
  license_plate_no: string
  vehicle_code: string
  estate_supplier: string
  division: string
  netto: number | null
  quantity: number | null
  note: string
}

/**
 * screen-011--form-grading business_logic step 3 — one editable
 * `grading_detail` row as held in FormGradingView.vue's form state before
 * saving. `id` is present for rows loaded from an existing draft (UPDATE
 * target) and absent for rows added in this editing session (INSERT
 * target) — `saveDraft()`/`pauseDraftWithFormData()` upsert on exactly
 * this distinction. `uom` is the snapshot copied at Quality Parameter
 * selection time (see `GradingDetailRow` above); `percentage` is
 * (re)computed by FormGradingView.vue immediately before every
 * save/pause call, not tracked live on this object between renders.
 */
export interface GradingDetailFormRow {
  id?: string
  grading_parameter_id: string
  quantity: number | null
  uom: string
  percentage: number | null
}

/**
 * User roles a caller may pass around this repo — kept as a plain string
 * union (not imported from stores/auth.ts's `AuthUser['role']`), matching
 * weighbridgeRecordRepo.ts's `WeighbridgeActorRole` so this repo stays
 * decoupled from the auth store. Currently unused by `saveDraft()`/
 * `pauseDraftWithFormData()` below (Form Grading has no role-gated
 * field — see this file's "entity-catalog v2 rewrite" update note above)
 * — kept exported for parity/possible future callers.
 */
export type GradingActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * screen-011--form-grading business_logic step 5 — thrown by `saveDraft()`
 * when `detailRows` is empty, so callers (FormGradingView.vue) can
 * distinguish this from other save failures and show the "at least one
 * grading detail row is required" error message rather than the generic
 * save-error banner. Named/exported (rather than a plain `Error`) so a
 * catch site can reliably `instanceof`-check it regardless of the
 * message text.
 */
export class GradingDetailRequiredError extends Error {
  constructor() {
    super('Minimal 1 baris grading detail harus diisi sebelum menyimpan.')
    this.name = 'GradingDetailRequiredError'
  }
}

interface CountRow {
  record_count: number
}

interface CurrentDraftRow {
  id: string
  status: GradingDraftStatus
}

const DRAFT_STATUSES: GradingDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

/**
 * Generates a local primary-key id for a new `grading_record` row. Same
 * approach/reasoning as weighbridgeRecordRepo.ts's `generateId()` —
 * `crypto.randomUUID()` where available (modern WebView / Node 19+,
 * matching this repo's Vitest/jsdom test environment), manual fallback for
 * older embedded WebViews.
 */
function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `grd-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

/**
 * Generates a local primary-key id for a new `grading_detail` row. Same
 * approach/reasoning as `generateId()` above, distinct prefix so ids
 * remain visually distinguishable between the two tables in ad-hoc
 * debugging.
 */
function generateDetailId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `grd-detail-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

/**
 * business_logic step 1 — count of grading_record rows for the current
 * user (running tally across the session, same "count everything this
 * user owns locally regardless of status" semantics as
 * weighbridgeRecordRepo.ts's `sumWbCard`), plus their current draft
 * (ongoing or paused, if any) so the UI can render a status badge.
 */
export interface GradingProgressSummary {
  recordCount: number
  currentDraft: CurrentDraft | null
}

/**
 * business_logic step 1 — count of local grading_record rows for the
 * current user (regardless of status, same running-tally semantics as
 * weighbridgeRecordRepo.ts's `getSummary()`), plus the current
 * ongoing/paused draft (if any) for the status badge. Computed with a SQL
 * aggregate rather than in JS so it stays a single round trip per query,
 * matching the rest of this app's local-repo style.
 */
export async function getProgressSummary(userId: string): Promise<GradingProgressSummary> {
  const [countRows, draftRows] = await Promise.all([
    query<CountRow>(`SELECT COUNT(*) AS record_count FROM grading_record WHERE created_by = ?`, [userId]),
    query<CurrentDraftRow>(
      `SELECT id, status
       FROM grading_record
       WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
       ORDER BY updated_at DESC
       LIMIT 1`,
      [userId],
    ),
  ])

  const draftRow = draftRows[0]

  return {
    recordCount: countRows[0]?.record_count ?? 0,
    currentDraft: draftRow ? { id: draftRow.id, status: draftRow.status } : null,
  }
}

/**
 * screen-008--monitor-grading "today's counter" addition — count + sums
 * across every local grading_record for the current user whose `date`
 * column falls on today's local (device) date, regardless of status (no
 * status filter, same as `getProgressSummary()`). Computed with a single
 * SQL aggregate query, matching this repo's existing
 * `getProgressSummary()` style and weighbridgeRecordRepo.ts's
 * `getTodaySummary()`. `date('now', 'localtime')` (rather than plain
 * `date('now')`, which is UTC) is used deliberately so "today" matches the
 * device's local day boundary, not UTC's.
 */
export async function getTodaySummary(userId: string): Promise<GradingTodaySummary> {
  const rows = await query<TodaySummaryAggregateRow>(
    `SELECT
       COUNT(*) AS count_grading,
       COALESCE(SUM(netto), 0) AS sum_netto,
       COALESCE(SUM(quantity), 0) AS sum_quantity
     FROM grading_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const aggregate = rows[0]

  return {
    countGrading: aggregate?.count_grading ?? 0,
    sumNetto: aggregate?.sum_netto ?? 0,
    sumQuantity: aggregate?.sum_quantity ?? 0,
  }
}

/**
 * screen-008--monitor-grading (list-view rewrite) business_logic step 2 —
 * every local `grading_record` the current user has ongoing or paused,
 * most-recently-updated first. Every returned row is labeled uniformly
 * "Pause" by the caller (MonitorGradingView.vue) — the ongoing/paused
 * distinction is carried in `status` purely for completeness/future use,
 * not surfaced as separate UI labels. Mirrors
 * weighbridgeRecordRepo.ts's `getDrafts()`.
 */
export async function getDrafts(userId: string): Promise<GradingDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, grading_number, updated_at
     FROM grading_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    grading_number: row.grading_number,
    updated_at: row.updated_at,
  }))
}

/**
 * screen-014--data-preview-grading (list-view) business_logic step 1 —
 * every local `grading_record` row for the current user, regardless of
 * status, most-recently-updated first. Returns the full row shape
 * (`GradingRecord`, same as `getDraftWithDetails()`'s header row) since
 * this screen's LIST mode filters/renders directly off it (grading_number/
 * license_plate_no/date/status) and its DETAIL mode transition just passes
 * an id onward (reusing `getDraftWithDetails()` for the actual detail
 * load — this function never touches `grading_detail`). Date/search
 * filtering is done client-side by the caller (in-memory, on this
 * already-fetched array) — this function never re-queries per filter
 * change, same as weighbridgeRecordRepo.ts's `getAllRecords()`.
 */
export async function getAllRecords(userId: string): Promise<GradingRecord[]> {
  return query<GradingRecord>(
    `SELECT * FROM grading_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * business_logic step 2 — 'Mulai Input Baru'. INSERTs a new
 * grading_record row with status='draft_ongoing', created_by=current
 * user, a generated id, and audit timestamps. All other fields
 * (grading_number, vehicle_number, driver_name, etc.) are left null here —
 * they are captured later by Form Grading (screen-011). Returns the new
 * record's id so the caller can navigate to Form Grading with it.
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO grading_record (id, status, created_by, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?)`,
    [id, userId, timestamp, timestamp],
  )

  return id
}

/**
 * business_logic step 3 — 'Lanjutkan draft paused'. UPDATEs the given
 * record's status back to 'draft_ongoing' (and updated_at). Returns the
 * updated record's id/status so the caller can navigate to Form Grading
 * with it.
 */
export async function resumeDraft(recordId: string): Promise<CurrentDraft> {
  const timestamp = nowIso()

  await run(`UPDATE grading_record SET status = 'draft_ongoing', updated_at = ? WHERE id = ?`, [
    timestamp,
    recordId,
  ])

  return { id: recordId, status: 'draft_ongoing' }
}

/**
 * business_logic step 4 — 'Pause'. UPDATEs the current ongoing record's
 * status to 'draft_paused' (and updated_at).
 *
 * Guard: when there is no ongoing draft to pause, this is a no-op — the
 * underlying `run()` write primitive must NOT be invoked at all (mirrors
 * weighbridgeRecordRepo.ts's `pauseDraft()` guard / the "Pause disabled
 * when no ongoing draft" business rule). The caller
 * (MonitorGradingView.vue) only ever has an id to pass when
 * `currentDraft.status === 'draft_ongoing'` (see the view's own disabled-
 * button guard), so `recordId` being falsy IS the "no ongoing draft"
 * signal here — checked and early-returned before any DB call.
 */
export async function pauseDraft(recordId: string | null | undefined): Promise<void> {
  if (!recordId) {
    return
  }

  const timestamp = nowIso()

  await run(
    `UPDATE grading_record SET status = 'draft_paused', updated_at = ? WHERE id = ? AND status = 'draft_ongoing'`,
    [timestamp, recordId],
  )
}

/**
 * business_logic step 5 — 'Clear' (after UI-level confirm). Permanently
 * DELETEs the record, cascading the delete to its child `grading_detail`
 * rows first (application-level cascade — no DB-level `ON DELETE CASCADE`
 * is declared in localSchema.ts; see that file's header comment). Runs as
 * two sequential DELETE statements via localDb.ts's `run()` (no
 * transaction primitive is exposed there, same as
 * weighbridgeRecordRepo.ts). Confirmation itself is a UI concern
 * (ConfirmDialog.vue / MonitorGradingView.vue) — this function deletes
 * unconditionally once called; the cancel path simply never calls it.
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM grading_detail WHERE grading_record_id = ?`, [recordId])
  await run(`DELETE FROM grading_record WHERE id = ?`, [recordId])
}

/**
 * screen-011--form-grading business_logic step 1 — Form Grading receives
 * a draft `id` via route param and loads that existing row (a draft
 * already INSERTed by Monitor Grading's 'Mulai Input Baru' / 'Lanjutkan',
 * screen-008) plus all of its `grading_detail` child rows. Returns `null`
 * when no header row matches (defensive — e.g. a stale/invalid route
 * param), letting the caller show a "not found" state instead of
 * throwing — same shape as weighbridgeRecordRepo.ts's `getDraftById()`,
 * extended with the child rows this screen also needs. Detail rows are
 * ordered by `created_at` so the grid renders them in the order they were
 * originally added.
 */
export async function getDraftWithDetails(recordId: string): Promise<GradingDraftWithDetails | null> {
  const recordRows = await query<GradingRecord>(`SELECT * FROM grading_record WHERE id = ?`, [recordId])
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const details = await query<GradingDetailRow>(
    `SELECT * FROM grading_detail WHERE grading_record_id = ? ORDER BY created_at ASC`,
    [recordId],
  )

  return { record, details }
}

/**
 * screen-011--form-grading — every local `weighbridge_record` row, any
 * status, most-recently-arrived first, for FormGradingView.vue's "WB Card
 * No" dropdown. Deliberately NOT scoped to `created_by` — see this file's
 * "entity-catalog v2 rewrite" update note above.
 */
export async function getWeighbridgeRecordOptions(): Promise<WeighbridgeRecordOption[]> {
  return query<WeighbridgeRecordOption>(
    `SELECT id, wb_card_number, arrival_datetime, vehicle_number, estate_supplier, division
     FROM weighbridge_record
     ORDER BY arrival_datetime DESC`,
  )
}

/**
 * screen-011--form-grading — every local `grading_parameter` row, ordered
 * by `sort_order`, for FormGradingView.vue's "Quality Parameter" dropdown
 * (one per `grading_detail` row).
 */
export async function getGradingParameterOptions(): Promise<GradingParameterOption[]> {
  return query<GradingParameterOption>(
    `SELECT id, name, uom, sort_order FROM grading_parameter ORDER BY sort_order ASC`,
  )
}

/**
 * screen-011--form-grading — upserts every row in `rows` against
 * `grading_detail` (rows with an existing `id` UPDATEd in place, rows
 * without one INSERTed with a freshly generated id via
 * `generateDetailId()`), then DELETEs every id in `idsToDelete` (rows the
 * user removed via "Hapus baris" on an already-loaded draft — see
 * FormGradingView.vue's pending-deletion tracking). Shared by
 * `saveDraft()` and `pauseDraftWithFormData()` below so both stay in
 * lock-step on this upsert/delete contract.
 */
async function applyDetailRowChanges(
  recordId: string,
  rows: GradingDetailFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    if (row.id) {
      await run(
        `UPDATE grading_detail
         SET grading_parameter_id = ?, quantity = ?, uom = ?, percentage = ?, updated_at = ?
         WHERE id = ?`,
        [row.grading_parameter_id || null, row.quantity, row.uom || null, row.percentage, timestamp, row.id],
      )
      continue
    }

    const detailId = generateDetailId()

    await run(
      `INSERT INTO grading_detail
         (id, grading_record_id, grading_parameter_id, quantity, uom, percentage, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [detailId, recordId, row.grading_parameter_id || null, row.quantity, row.uom || null, row.percentage, timestamp, timestamp],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM grading_detail WHERE id = ?`, [id])
  }
}

/**
 * screen-011--form-grading business_logic — 'Simpan'.
 *
 * Throws `GradingDetailRequiredError` when zero rows in `detailRows`
 * qualify as "valid" (a `grading_parameter_id` selected AND a non-null/
 * non-undefined `quantity`) — enforced here (not only as a client-side
 * pre-check in FormGradingView.vue) so the rule holds even if a caller
 * bypasses the UI. No DB write happens at all in that case. Required-
 * header-field validation is the caller's (FormGradingView.vue's) job —
 * this function assumes the header has already been validated.
 *
 * On success: UPDATEs the header row (status='saved', updated_at=now,
 * `checked_by`/`acknowledged_by` untouched — see this file's
 * "entity-catalog v2 rewrite" update note above), then applies
 * `detailRows`'s upserts and `detailIdsToDelete`'s deletes via
 * `applyDetailRowChanges()`.
 *
 * Sequential `run()` calls, not a single transaction — see this file's
 * header comment ("Transaction handling").
 *
 * Returns the freshly saved draft (re-read via getDraftWithDetails()) so
 * the caller can rely on the persisted state rather than re-deriving it
 * locally.
 */
export async function saveDraft(
  recordId: string,
  headerData: GradingHeaderFormData,
  detailRows: GradingDetailFormRow[],
  detailIdsToDelete: string[],
): Promise<GradingDraftWithDetails> {
  const validDetailRowCount = detailRows.filter(
    (row) => Boolean(row.grading_parameter_id) && row.quantity !== null && row.quantity !== undefined,
  ).length

  if (validDetailRowCount === 0) {
    throw new GradingDetailRequiredError()
  }

  const timestamp = nowIso()

  await run(
    `UPDATE grading_record
     SET grading_number = ?,
         date = ?,
         weighbridge_record_id = ?,
         license_plate_no = ?,
         vehicle_code = ?,
         estate_supplier = ?,
         division = ?,
         netto = ?,
         quantity = ?,
         note = ?,
         status = 'saved',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.grading_number || null,
      headerData.date || null,
      headerData.weighbridge_record_id || null,
      headerData.license_plate_no || null,
      headerData.vehicle_code || null,
      headerData.estate_supplier || null,
      headerData.division || null,
      headerData.netto,
      headerData.quantity,
      headerData.note || null,
      timestamp,
      recordId,
    ],
  )

  await applyDetailRowChanges(recordId, detailRows, detailIdsToDelete, timestamp)

  const saved = await getDraftWithDetails(recordId)

  if (!saved) {
    throw new Error('Gagal memuat ulang data grading setelah disimpan.')
  }

  return saved
}

/**
 * screen-011--form-grading business_logic — 'Pause'. UPDATEs every header
 * field on the given record (same column set as `saveDraft()`, minus
 * `checked_by`/`acknowledged_by` — see this file's "entity-catalog v2
 * rewrite" update note above) as-is, then sets status='draft_paused'/
 * updated_at=now — NO required-field validation, a checkpoint save (same
 * split as weighbridgeRecordRepo.ts's `saveDraft()` vs
 * `pauseDraftWithFormData()`). Applies `detailRows`'s upserts and
 * `detailIdsToDelete`'s deletes via `applyDetailRowChanges()`, identical
 * semantics to `saveDraft()`, but with no gate before it runs.
 *
 * Returns the freshly paused draft (re-read via getDraftWithDetails()),
 * same pattern as `saveDraft()`.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: GradingHeaderFormData,
  detailRows: GradingDetailFormRow[],
  detailIdsToDelete: string[],
): Promise<GradingDraftWithDetails> {
  const timestamp = nowIso()

  await run(
    `UPDATE grading_record
     SET grading_number = ?,
         date = ?,
         weighbridge_record_id = ?,
         license_plate_no = ?,
         vehicle_code = ?,
         estate_supplier = ?,
         division = ?,
         netto = ?,
         quantity = ?,
         note = ?,
         status = 'draft_paused',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.grading_number || null,
      headerData.date || null,
      headerData.weighbridge_record_id || null,
      headerData.license_plate_no || null,
      headerData.vehicle_code || null,
      headerData.estate_supplier || null,
      headerData.division || null,
      headerData.netto,
      headerData.quantity,
      headerData.note || null,
      timestamp,
      recordId,
    ],
  )

  await applyDetailRowChanges(recordId, detailRows, detailIdsToDelete, timestamp)

  const saved = await getDraftWithDetails(recordId)

  if (!saved) {
    throw new Error('Gagal memuat ulang data grading setelah disimpan (Pause).')
  }

  return saved
}

export const gradingRecordRepo = {
  getProgressSummary,
  getTodaySummary,
  getDrafts,
  getAllRecords,
  createDraft,
  resumeDraft,
  pauseDraft,
  deleteDraft,
  getDraftWithDetails,
  getWeighbridgeRecordOptions,
  getGradingParameterOptions,
  saveDraft,
  pauseDraftWithFormData,
}

export default gradingRecordRepo

// Re-exported for readability at call sites that only need the status enum.
export const GRADING_DRAFT_STATUSES = DRAFT_STATUSES
