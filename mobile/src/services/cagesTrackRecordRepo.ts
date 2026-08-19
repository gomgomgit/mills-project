import { query, run } from '@/services/localDb'

/**
 * cagesTrackRecordRepo — screen-009--monitor-cages-track /
 * usecase-009--monitor-cages-track business_logic steps 1-5. Local
 * (offline) read/write access to the `cages_track_record` table (schema
 * defined in localSchema.ts) — mirrors gradingRecordRepo.ts's repo style
 * (plain async functions over localDb.ts's `query`/`run` primitives) as
 * closely as sensible given cages-track's different business logic.
 *
 * Difference from gradingRecordRepo.ts: Monitor Cages Track's progress
 * summary (business_logic step 1) is "jumlah cage/lori tercatat pada sesi
 * berjalan" — a COUNT of the CHILD `cages_tipped_time` rows belonging to
 * the current session's draft, not a count of `cages_track_record` rows
 * themselves (grading's `recordCount` counts the parent table). When there
 * is no current draft, the tipped count is simply 0 (nothing to count
 * against). `deleteDraft()` also cascades the delete to `cages_tipped_time`
 * rows, same application-level-cascade approach as
 * gradingRecordRepo.ts's `deleteDraft()` → `grading_detail`.
 *
 * Update (screen-012--form-cages-track): adds `getDraftWithTippedTimes()`
 * (SELECT the `cages_track_record` header row plus all of its
 * `cages_tipped_time` child rows, for Form Cages Track to load a draft on
 * mount — mirrors gradingRecordRepo.ts's `getDraftWithDetails()`) and
 * `saveDraft()` (business_logic steps 4-7 — validates at least one
 * cages_tipped_time row exists, UPDATEs the header with status='saved',
 * and upserts every tipped-time row: rows with an existing `id` are
 * UPDATEd, rows without one are INSERTed with a freshly generated id).
 * `saveDraft()` is also where business_logic step 6's role-based
 * `checked_by` stripping is enforced — deliberately at this repo level
 * (not only in FormCagesTrackView.vue's UI), same reasoning as
 * gradingRecordRepo.ts's `saveDraft()`.
 *
 * Transaction handling: `localDb.ts` exposes only `query()`/`run()` — no
 * transaction primitive (see its header comment; still true as of this
 * screen). `saveDraft()` therefore performs the header UPDATE followed by
 * each tipped-time row's UPDATE/INSERT as sequential `run()` calls (header
 * first, then rows in array order) rather than a single atomic
 * transaction — same documented limitation as gradingRecordRepo.ts's
 * `saveDraft()` and this file's own `deleteDraft()`. A mid-sequence
 * failure can leave the header saved with only a subset of tipped-time
 * rows persisted. Acceptable for this stage (single-user local device, no
 * concurrent writers) but noted here for anyone adding a real transaction
 * primitive to localDb.ts later.
 *
 * Update (screen-009--monitor-cages-track, list-view + entity-catalog v3
 * rewrite): adds `getDrafts()` — business_logic step 2 of the rewritten
 * Monitor Cages Track screen, which now shows a full scrollable list of
 * the current user's ongoing/paused drafts (every row labeled uniformly
 * "Pause" in the UI, no ongoing-vs-paused distinction), mirroring
 * gradingRecordRepo.ts's `getDrafts()` (screen-008's own equivalent
 * list-view rewrite). Also adds `getTodaySummary()` — the screen's new
 * "Hari Ini" 2-card counter section (business_logic step 1): "Jumlah
 * Cages Track" is a COUNT of the current user's `cages_track_record` rows
 * (any status) whose `date(date)` is today's local device date, and
 * "Jumlah Cage/Lori Tercatat" is a SUM of `total_cages` across every
 * `cages_tipped_time` row belonging to those records — NOT a COUNT of
 * `cages_tipped_time` rows, since a single tipped-time row can represent
 * multiple cages tipped at once (entity-catalog v3's `total_cages`
 * column). `localDb.ts` has no JOIN helper (see its header comment), so
 * this is computed via two sequential queries: (a) today's
 * `cages_track_record` ids for the user, (b) `SUM(total_cages)` from
 * `cages_tipped_time` scoped to an `IN (...)` of those ids — step (b) is
 * skipped entirely (returning 0 without querying) when step (a) finds no
 * ids, since an empty `IN ()` list is invalid SQL. This differs from
 * gradingRecordRepo.ts's `getTodaySummary()` (a single-query aggregate
 * over columns that live directly on `grading_record`) precisely because
 * "cages tipped" lives on the CHILD table here, not the parent —
 * mirrors this file's own pre-existing `getProgressSummary()`
 * two-round-trip shape (draft lookup, then a scoped child-table read) for
 * the same underlying reason, adapted to "today across all statuses"
 * instead of "the current draft".
 *
 * `createDraft()` also gains a `tippler_start_time` auto-fill (set once,
 * at draft creation, to the same timestamp as `created_at`/`updated_at`)
 * — a new entity-catalog v3 business rule not present in the pre-v3
 * shape/weighbridge's or grading's own `createDraft()` (neither has an
 * equivalent auto-filled timestamp column). `getProgressSummary()` is
 * left in place unchanged (still used by StationListView.vue's
 * draft-status-by-type lookup); `resumeDraft()`/`pauseDraft()`/
 * `deleteDraft()` are no longer called by this screen going forward but
 * are kept for parity with gradingRecordRepo.ts's own equivalents, and in
 * case another caller needs them later. Purely additive to `createDraft()`
 * (one new column) plus two new exports — every other exported
 * function/type in this file at that time (including the entire
 * screen-012/014 block, which still assumed the pre-v3 `CagesTrackRecord`/
 * `CagesTippedTimeRow` column set) was left untouched.
 *
 * Update (screen-012--form-cages-track, entity-catalog v3 rewrite,
 * 2026-08-19): the screen-012--form-cages-track block below is now FULLY
 * REVISED for entity-catalog v3's restructured shape (see
 * localSchema.ts's CREATE_CAGES_TRACK_RECORD / CREATE_CAGES_TIPPED_TIME
 * comments) — this is a hard behavioral change, not additive:
 *  - `CagesTrackRecord` gains `tippler_start_time`/`tippler_stop_time`/
 *    `cages_out`/`cages_tipped`/`note`.
 *  - `CagesTippedTimeRow`/`CagesTippedTimeFormRow` DROP the old
 *    `cage_number`/`tipped_time` columns entirely, replaced by
 *    `tipped_hour` (INTEGER 0-23, one row = one hour slot),
 *    `checked_cage_numbers` (TEXT, comma-separated cage numbers checked
 *    in that hour, e.g. "1,3,5"), `total_cages` (COUNT of checked cage
 *    numbers) and `cages_remain` (header's `cages_tipped` minus this
 *    row's own `total_cages`) — both computed client-side by
 *    FormCagesTrackView.vue immediately before every save/pause call
 *    (via its own `buildTippedTimeRowsPayload()`), same "computed then
 *    persisted" pattern as gradingRecordRepo.ts's `percentage` — this
 *    repo never recomputes either column itself, it just persists
 *    whatever `total_cages`/`cages_remain` the caller supplies.
 *  - `saveDraft()`'s "at least one row required" check is now "at least
 *    one row with a selected `tipped_hour` AND at least one checked cage
 *    (`total_cages > 0`)", not merely "array non-empty" (an empty grid AND
 *    a grid full of rows with no Time/no checked cages both throw
 *    `CagesTippedTimeRequiredError`).
 *  - `saveDraft()` now also strips `acknowledged_by` by role (only kept
 *    when `currentUserRole === 'mill_management'`), in addition to the
 *    pre-existing `checked_by` (`supervisor`-only) stripping — both
 *    role-gated columns per this screen's tech spec Verifikasi section.
 *  - New `pauseDraftWithFormData()` (mirrors gradingRecordRepo.ts's own
 *    function of the same name) — a checkpoint save, NO required-field
 *    validation, `status='draft_paused'`, and deliberately does NOT
 *    special-case `tippler_stop_time` (it just persists whatever
 *    `headerData.tippler_stop_time` already holds, which
 *    FormCagesTrackView.vue never mutates during Pause — see that file's
 *    header comment). Also applies the same `checked_by`/`acknowledged_by`
 *    role-stripping as `saveDraft()`, as defense-in-depth (Form Cages
 *    Track's tech spec role-gates both fields at all times, not only at
 *    Simpan).
 *  - New shared `applyTippedTimeRowChanges()` helper (mirrors
 *    gradingRecordRepo.ts's `applyDetailRowChanges()`) — upserts every
 *    row (has-`id` rows UPDATEd, no-`id` rows INSERTed with a freshly
 *    generated id via the pre-existing `generateTippedTimeId()`), then
 *    DELETEs every id in the caller-supplied pending-deletion list. Used
 *    by both `saveDraft()` and `pauseDraftWithFormData()` so the two stay
 *    in lock-step on this upsert/delete contract — same reasoning as
 *    grading's shared helper.
 *  - `tippler_stop_time`'s "freeze to now, only at the exact moment Simpan
 *    succeeds" business rule is deliberately implemented in
 *    FormCagesTrackView.vue's `onSimpan()`, NOT in this repo —
 *    `saveDraft()` just writes whatever `headerData.tippler_stop_time`
 *    value it's given, same "assume the header has already been
 *    prepared by the caller" contract as every other header field. See
 *    that file's header comment for why this differs from
 *    weighbridgeRecordRepo.ts's live-ticking-then-frozen
 *    `dispatch_datetime` (this column never ticks at all — it stays
 *    blank until the instant of a successful Simpan).
 *  - Transaction handling / sequential-`run()`-calls limitation (see this
 *    file's header comment above) is unchanged and still applies to every
 *    function in this updated block.
 *
 * Update (screen-015--data-preview-cages-track, list-view rewrite): adds
 * `getAllRecords()` — Data Preview Cages Track's new LIST mode
 * (business_logic step 1) needs every local `cages_track_record` row for
 * the current user across ALL statuses (draft_ongoing/draft_paused/
 * saved/synced), unlike `getDrafts()` above which intentionally filters
 * to only draft_ongoing/draft_paused for Monitor Cages Track's own list.
 * Mirrors gradingRecordRepo.ts's own `getAllRecords()`
 * (screen-014--data-preview-grading) column-for-column, adapted to
 * `cages_track_record`'s entity-catalog v3 shape (`CagesTrackRecord`, the
 * same full-row type `getDraftWithTippedTimes()` already uses). DETAIL
 * mode continues to reuse `getDraftWithTippedTimes()` unchanged — this
 * function is for the LIST-mode summary array only, it does not touch
 * `cages_tipped_time` at all. Purely additive — every other exported
 * function/type in this file (including the entire screen-009/012 blocks
 * above) is unchanged.
 */

export type CagesTrackDraftStatus = 'draft_ongoing' | 'draft_paused'
export type CagesTrackRecordStatus = CagesTrackDraftStatus | 'saved' | 'synced'

export interface CurrentDraft {
  id: string
  status: CagesTrackDraftStatus
}

/**
 * screen-012--form-cages-track (entity-catalog v3) — full local
 * `cages_track_record` row shape (mirrors localSchema.ts's
 * CREATE_CAGES_TRACK_RECORD column-for-column). `getDraftWithTippedTimes()`/
 * `saveDraft()`/`pauseDraftWithFormData()` below operate on this shape.
 */
export interface CagesTrackRecord {
  id: string
  station_id: string | null
  cages_track_number: string | null
  date: string | null
  tippler_start_time: string | null
  tippler_stop_time: string | null
  cages_out: number | null
  cages_tipped: number | null
  note: string | null
  checked_by: string | null
  acknowledged_by: string | null
  status: CagesTrackRecordStatus
  created_by: string
  created_at: string
  updated_at: string
}

/**
 * screen-012--form-cages-track (entity-catalog v3) — full local
 * `cages_tipped_time` row shape (mirrors localSchema.ts's
 * CREATE_CAGES_TIPPED_TIME column-for-column). FULL RESTRUCTURE from the
 * pre-v3 "one row per cage" shape (`cage_number`/`tipped_time`, both
 * REMOVED) to "one row per hour slot with a checklist of cages tipped in
 * that hour" — see localSchema.ts's own CREATE_CAGES_TIPPED_TIME comment
 * for the full column-level rationale. `total_cages`/`cages_remain` are
 * computed client-side by FormCagesTrackView.vue and persisted as-is —
 * this repo never recomputes either.
 */
export interface CagesTippedTimeRow {
  id: string
  cages_track_record_id: string
  tipped_hour: number | null
  checked_cage_numbers: string | null
  total_cages: number | null
  cages_remain: number | null
  created_at: string
  updated_at: string
}

/**
 * screen-012--form-cages-track business_logic step 1 — a
 * `cages_track_record` header row plus all of its `cages_tipped_time`
 * child rows, as loaded by `getDraftWithTippedTimes()` and rendered by
 * FormCagesTrackView.vue.
 */
export interface CagesTrackDraftWithTippedTimes {
  record: CagesTrackRecord
  tippedTimes: CagesTippedTimeRow[]
}

/**
 * screen-012--form-cages-track (entity-catalog v3) business_logic step 4
 * — the subset of `CagesTrackRecord` columns the Form Cages Track screen
 * actually collects from the user (excludes id/station_id/created_by/
 * created_at/updated_at/status, which are managed by
 * createDraft()/resumeDraft()/saveDraft() itself, not user-entered).
 * `date`/`tippler_start_time` are always rendered disabled by
 * FormCagesTrackView.vue (auto-set-once-if-new) but still round-trip
 * through this shape since they're real persisted columns.
 * `tippler_stop_time` stays blank in form state until the exact moment a
 * Simpan call succeeds (see this file's header comment "Update
 * (screen-012...)").
 */
export interface CagesTrackHeaderFormData {
  cages_track_number: string
  date: string
  tippler_start_time: string
  tippler_stop_time: string
  cages_out: number | null
  cages_tipped: number | null
  note: string
  checked_by: string
  acknowledged_by: string
}

/**
 * screen-012--form-cages-track (entity-catalog v3) business_logic step 3
 * — one editable `cages_tipped_time` row as held in
 * FormCagesTrackView.vue's form state before saving. `id` is present for
 * rows loaded from an existing draft (UPDATE target) and absent for rows
 * added in this editing session (INSERT target) — `saveDraft()`/
 * `pauseDraftWithFormData()` upsert on exactly this distinction.
 * `total_cages`/`cages_remain` are (re)computed by
 * FormCagesTrackView.vue's `buildTippedTimeRowsPayload()` immediately
 * before every save/pause call (from `checked_cage_numbers` + the
 * header's `cages_tipped`), not tracked live on this object between
 * renders — same "computed fresh right before save" pattern as
 * gradingRecordRepo.ts's `GradingDetailFormRow.percentage`.
 */
export interface CagesTippedTimeFormRow {
  id?: string
  tipped_hour: number | null
  checked_cage_numbers: string
  total_cages: number
  cages_remain: number
}

/**
 * User roles a `saveDraft()`/`pauseDraftWithFormData()` caller may pass as
 * `currentUserRole` — kept as a plain string union (not imported from
 * stores/auth.ts's `AuthUser['role']`), matching gradingRecordRepo.ts's
 * `GradingActorRole` so this repo stays decoupled from the auth store.
 * Callers pass `authStore.currentUser?.role` straight through.
 */
export type CagesTrackActorRole = 'operator' | 'supervisor' | 'mill_management' | 'admin'

/**
 * screen-012--form-cages-track business_logic step 5 — thrown by
 * `saveDraft()` when zero rows in `tippedTimeRows` are "valid" (a selected
 * `tipped_hour` AND at least one checked cage, i.e. `total_cages > 0`), so
 * callers (FormCagesTrackView.vue) can distinguish this from other save
 * failures and show the "at least one cages tipped time row is required"
 * error message rather than the generic save-error banner. Named/exported
 * (rather than a plain `Error`) so a catch site can reliably
 * `instanceof`-check it regardless of the message text.
 */
export class CagesTippedTimeRequiredError extends Error {
  constructor() {
    super('Minimal 1 baris cages tipped time harus diisi sebelum menyimpan.')
    this.name = 'CagesTippedTimeRequiredError'
  }
}

/**
 * business_logic step 1 — count of `cages_tipped_time` rows for the
 * current session's draft (ongoing or paused), plus the draft itself (if
 * any) so the UI can render a status badge. Scoped to the current user via
 * the draft lookup (`created_by = current user id`).
 */
export interface CagesTrackProgressSummary {
  tippedCount: number
  currentDraft: CurrentDraft | null
}

interface CountRow {
  tipped_count: number
}

interface CurrentDraftRow {
  id: string
  status: CagesTrackDraftStatus
}

/**
 * screen-009--monitor-cages-track (list-view rewrite) "today's counter"
 * addition — one row of `getTodaySummary()`'s result (see that function's
 * doc comment).
 */
export interface CagesTrackTodaySummary {
  countCagesTrack: number
  sumTotalCages: number
}

interface TodayRecordIdRow {
  id: string
}

interface SumTotalCagesRow {
  sum_cages: number | null
}

/**
 * screen-009--monitor-cages-track (list-view rewrite) business_logic step
 * 2 — one row of the current user's ongoing/paused draft list.
 * `cages_track_number` is included (unlike `CurrentDraft`/`CurrentDraftRow`
 * above) so the list can render each draft's Cages Track Number, falling
 * back to a placeholder in the view when null/empty — mirrors
 * gradingRecordRepo.ts's `GradingDraftListItem`/`grading_number`.
 */
export interface CagesTrackDraftListItem {
  id: string
  status: CagesTrackDraftStatus
  cages_track_number: string | null
  updated_at: string
}

interface DraftListRow {
  id: string
  status: CagesTrackDraftStatus
  cages_track_number: string | null
  updated_at: string
}

const DRAFT_STATUSES: CagesTrackDraftStatus[] = ['draft_ongoing', 'draft_paused']

function nowIso(): string {
  return new Date().toISOString()
}

/**
 * Generates a local primary-key id for a new `cages_track_record` row.
 * Same approach/reasoning as gradingRecordRepo.ts's `generateId()` —
 * `crypto.randomUUID()` where available (modern WebView / Node 19+,
 * matching this repo's Vitest/jsdom test environment), manual fallback for
 * older embedded WebViews.
 */
function generateId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `ctr-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

/**
 * Generates a local primary-key id for a new `cages_tipped_time` row. Same
 * approach/reasoning as `generateId()` above, distinct prefix so ids
 * remain visually distinguishable between the two tables in ad-hoc
 * debugging.
 */
function generateTippedTimeId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `ctt-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

/**
 * business_logic step 1 — finds the current user's ongoing/paused
 * `cages_track_record` draft (if any), then counts local
 * `cages_tipped_time` rows belonging to that draft ("jumlah cage/lori
 * tercatat pada sesi berjalan"). Two round trips (draft lookup, then a
 * scoped count) rather than one join, kept simple/explicit and mirroring
 * gradingRecordRepo.ts's `Promise.all` style where the two reads are
 * independent — here the count genuinely depends on the draft's id, so
 * the count query only runs once the draft (or its absence) is known.
 */
export async function getProgressSummary(userId: string): Promise<CagesTrackProgressSummary> {
  const draftRows = await query<CurrentDraftRow>(
    `SELECT id, status
     FROM cages_track_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC
     LIMIT 1`,
    [userId],
  )

  const draftRow = draftRows[0]

  if (!draftRow) {
    return { tippedCount: 0, currentDraft: null }
  }

  const countRows = await query<CountRow>(
    `SELECT COUNT(*) AS tipped_count FROM cages_tipped_time WHERE cages_track_record_id = ?`,
    [draftRow.id],
  )

  return {
    tippedCount: countRows[0]?.tipped_count ?? 0,
    currentDraft: { id: draftRow.id, status: draftRow.status },
  }
}

/**
 * business_logic step 5 — 'New Data' (Monitor Cages Track). INSERTs a new
 * cages_track_record row with status='draft_ongoing', created_by=current
 * user, a generated id, audit timestamps, AND `tippler_start_time` set to
 * that same timestamp — entity-catalog v3's business rule that the
 * tippler start time auto-fills once, at draft creation (this is new vs.
 * the pre-v3 shape / weighbridgeRecordRepo.ts's/gradingRecordRepo.ts's own
 * `createDraft()`, neither of which auto-fills a timestamp column). All
 * other fields (cages_track_number, date, tippler_stop_time, cages_out,
 * cages_tipped, note, checked_by, acknowledged_by, etc.) are left null
 * here — they are captured later by Form Cages Track (screen-012). Returns
 * the new record's id so the caller can navigate to Form Cages Track with
 * it.
 */
export async function createDraft(userId: string): Promise<string> {
  const id = generateId()
  const timestamp = nowIso()

  await run(
    `INSERT INTO cages_track_record (id, status, created_by, tippler_start_time, created_at, updated_at)
     VALUES (?, 'draft_ongoing', ?, ?, ?, ?)`,
    [id, userId, timestamp, timestamp, timestamp],
  )

  return id
}

/**
 * screen-009--monitor-cages-track (list-view rewrite) business_logic step
 * 1 — "Hari Ini" counters. "Jumlah Cages Track" counts the current user's
 * `cages_track_record` rows (any status) dated today (device-local day
 * boundary, `date('now', 'localtime')` — same reasoning as
 * gradingRecordRepo.ts's own `getTodaySummary()`). "Jumlah Cage/Lori
 * Tercatat" sums `total_cages` across every `cages_tipped_time` row
 * belonging to those same records (a SUM, not a COUNT of
 * `cages_tipped_time` rows — one row can represent multiple cages tipped
 * at once). `localDb.ts` exposes no JOIN helper (see its header comment),
 * so this runs as two sequential queries: first the today-scoped record
 * ids, then a `SUM(total_cages) ... WHERE cages_track_record_id IN (...)`
 * scoped to exactly those ids. When there are no matching ids, the second
 * query is skipped entirely (an empty `IN ()` list is invalid SQL) and the
 * sum is simply 0.
 */
export async function getTodaySummary(userId: string): Promise<CagesTrackTodaySummary> {
  const idRows = await query<TodayRecordIdRow>(
    `SELECT id
     FROM cages_track_record
     WHERE created_by = ? AND date(date) = date('now', 'localtime')`,
    [userId],
  )

  const countCagesTrack = idRows.length

  if (countCagesTrack === 0) {
    return { countCagesTrack: 0, sumTotalCages: 0 }
  }

  const placeholders = idRows.map(() => '?').join(', ')

  const sumRows = await query<SumTotalCagesRow>(
    `SELECT COALESCE(SUM(total_cages), 0) AS sum_cages
     FROM cages_tipped_time
     WHERE cages_track_record_id IN (${placeholders})`,
    idRows.map((row) => row.id),
  )

  return {
    countCagesTrack,
    sumTotalCages: sumRows[0]?.sum_cages ?? 0,
  }
}

/**
 * screen-009--monitor-cages-track (list-view rewrite) business_logic step
 * 2 — every local `cages_track_record` the current user has ongoing or
 * paused, most-recently-updated first. Every returned row is labeled
 * uniformly "Pause" by the caller (MonitorCagesTrackView.vue) — the
 * ongoing/paused distinction is carried in `status` purely for
 * completeness/future use, not surfaced as separate UI labels. Mirrors
 * gradingRecordRepo.ts's `getDrafts()`.
 */
export async function getDrafts(userId: string): Promise<CagesTrackDraftListItem[]> {
  const rows = await query<DraftListRow>(
    `SELECT id, status, cages_track_number, updated_at
     FROM cages_track_record
     WHERE created_by = ? AND status IN ('draft_ongoing', 'draft_paused')
     ORDER BY updated_at DESC`,
    [userId],
  )

  return rows.map((row) => ({
    id: row.id,
    status: row.status,
    cages_track_number: row.cages_track_number,
    updated_at: row.updated_at,
  }))
}

/**
 * screen-015--data-preview-cages-track (list-view rewrite) business_logic
 * step 1 — every local `cages_track_record` row for the current user,
 * ANY status, most-recently-updated first. Unlike `getDrafts()` above
 * (which filters to draft_ongoing/draft_paused only), this returns the
 * full history for Data Preview Cages Track's LIST mode — see this file's
 * header comment "Update (screen-015...)" for the full rationale. This
 * function never touches `cages_tipped_time` — DETAIL mode's own child
 * rows are loaded separately via `getDraftWithTippedTimes()` on demand.
 */
export async function getAllRecords(userId: string): Promise<CagesTrackRecord[]> {
  return query<CagesTrackRecord>(
    `SELECT * FROM cages_track_record WHERE created_by = ? ORDER BY updated_at DESC`,
    [userId],
  )
}

/**
 * business_logic step 3 — 'Lanjutkan draft paused'. UPDATEs the given
 * record's status back to 'draft_ongoing' (and updated_at). Returns the
 * updated record's id/status so the caller can navigate to Form Cages
 * Track with it.
 */
export async function resumeDraft(recordId: string): Promise<CurrentDraft> {
  const timestamp = nowIso()

  await run(`UPDATE cages_track_record SET status = 'draft_ongoing', updated_at = ? WHERE id = ?`, [
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
 * gradingRecordRepo.ts's `pauseDraft()` guard / the "Pause disabled when
 * no ongoing draft" business rule). The caller
 * (MonitorCagesTrackView.vue) only ever has an id to pass when
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
    `UPDATE cages_track_record SET status = 'draft_paused', updated_at = ? WHERE id = ? AND status = 'draft_ongoing'`,
    [timestamp, recordId],
  )
}

/**
 * business_logic step 5 — 'Clear' (after UI-level confirm). Permanently
 * DELETEs the record, cascading the delete to its child
 * `cages_tipped_time` rows first (application-level cascade — no DB-level
 * `ON DELETE CASCADE` is declared in localSchema.ts; see that file's
 * header comment). Runs as two sequential DELETE statements via
 * localDb.ts's `run()` (no transaction primitive is exposed there, same
 * as gradingRecordRepo.ts). Confirmation itself is a UI concern
 * (ConfirmDialog.vue / MonitorCagesTrackView.vue) — this function deletes
 * unconditionally once called; the cancel path simply never calls it.
 */
export async function deleteDraft(recordId: string): Promise<void> {
  await run(`DELETE FROM cages_tipped_time WHERE cages_track_record_id = ?`, [recordId])
  await run(`DELETE FROM cages_track_record WHERE id = ?`, [recordId])
}

/**
 * screen-012--form-cages-track business_logic step 1 — Form Cages Track
 * receives a draft `id` via route param and loads that existing row (a
 * draft already INSERTed by Monitor Cages Track's 'Mulai Input Baru' /
 * 'Lanjutkan', screen-009) plus all of its `cages_tipped_time` child rows.
 * Returns `null` when no header row matches (defensive — e.g. a
 * stale/invalid route param), letting the caller show a "not found" state
 * instead of throwing — same shape as gradingRecordRepo.ts's
 * `getDraftWithDetails()`, extended with the child rows this screen also
 * needs. Tipped-time rows are ordered by `created_at` so the grid renders
 * them in the order they were originally added.
 */
export async function getDraftWithTippedTimes(recordId: string): Promise<CagesTrackDraftWithTippedTimes | null> {
  const recordRows = await query<CagesTrackRecord>(`SELECT * FROM cages_track_record WHERE id = ?`, [recordId])
  const record = recordRows[0]

  if (!record) {
    return null
  }

  const tippedTimes = await query<CagesTippedTimeRow>(
    `SELECT * FROM cages_tipped_time WHERE cages_track_record_id = ? ORDER BY created_at ASC`,
    [recordId],
  )

  return { record, tippedTimes }
}

/**
 * screen-012--form-cages-track — upserts every row in `rows` against
 * `cages_tipped_time` (rows with an existing `id` UPDATEd in place, rows
 * without one INSERTed with a freshly generated id via
 * `generateTippedTimeId()`), then DELETEs every id in `idsToDelete` (rows
 * the user removed via "Hapus baris" on an already-loaded draft — see
 * FormCagesTrackView.vue's pending-deletion tracking). `total_cages`/
 * `cages_remain` are written exactly as given by the caller (computed
 * client-side, see `CagesTippedTimeFormRow`'s own doc comment) — this
 * function never recomputes either. Shared by `saveDraft()` and
 * `pauseDraftWithFormData()` below so both stay in lock-step on this
 * upsert/delete contract — mirrors gradingRecordRepo.ts's
 * `applyDetailRowChanges()`.
 */
async function applyTippedTimeRowChanges(
  recordId: string,
  rows: CagesTippedTimeFormRow[],
  idsToDelete: string[],
  timestamp: string,
): Promise<void> {
  for (const row of rows) {
    if (row.id) {
      await run(
        `UPDATE cages_tipped_time
         SET tipped_hour = ?, checked_cage_numbers = ?, total_cages = ?, cages_remain = ?, updated_at = ?
         WHERE id = ?`,
        [row.tipped_hour, row.checked_cage_numbers || null, row.total_cages, row.cages_remain, timestamp, row.id],
      )
      continue
    }

    const tippedTimeId = generateTippedTimeId()

    await run(
      `INSERT INTO cages_tipped_time
         (id, cages_track_record_id, tipped_hour, checked_cage_numbers, total_cages, cages_remain, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        tippedTimeId,
        recordId,
        row.tipped_hour,
        row.checked_cage_numbers || null,
        row.total_cages,
        row.cages_remain,
        timestamp,
        timestamp,
      ],
    )
  }

  for (const id of idsToDelete) {
    await run(`DELETE FROM cages_tipped_time WHERE id = ?`, [id])
  }
}

/**
 * screen-012--form-cages-track (entity-catalog v3) business_logic steps
 * 11 — 'Simpan'.
 *
 * Throws `CagesTippedTimeRequiredError` when zero rows in `tippedTimeRows`
 * qualify as "valid" (a selected `tipped_hour` AND `total_cages > 0`,
 * i.e. at least one checked cage) — enforced here (not only as a
 * client-side pre-check in FormCagesTrackView.vue) so the rule holds even
 * if a caller bypasses the UI. No DB write happens at all in that case.
 * Required-header-field validation (`cages_track_number`/`cages_out`/
 * `cages_tipped`) and freezing `tippler_stop_time` to "now" are the
 * caller's (FormCagesTrackView.vue's) job — this function assumes the
 * header has already been validated/prepared (see this file's header
 * comment "Update (screen-012...)").
 *
 * `checked_by`/`acknowledged_by` role-stripping: whenever
 * `currentUserRole !== 'supervisor'`, `headerData.checked_by` is ignored
 * and `null` is written instead; whenever `currentUserRole !==
 * 'mill_management'`, `headerData.acknowledged_by` is likewise ignored —
 * same defense-in-depth pattern as gradingRecordRepo.ts's `saveDraft()`,
 * extended here to two role-gated columns instead of one.
 *
 * On success: UPDATEs the header row (status='saved', updated_at=now),
 * then applies `tippedTimeRows`'s upserts and `tippedTimeIdsToDelete`'s
 * deletes via `applyTippedTimeRowChanges()`.
 *
 * Sequential `run()` calls, not a single transaction — see this file's
 * header comment ("Transaction handling").
 *
 * Returns the freshly saved draft (re-read via getDraftWithTippedTimes())
 * so the caller can rely on the persisted state rather than re-deriving it
 * locally.
 */
export async function saveDraft(
  recordId: string,
  headerData: CagesTrackHeaderFormData,
  tippedTimeRows: CagesTippedTimeFormRow[],
  tippedTimeIdsToDelete: string[],
  currentUserRole: CagesTrackActorRole | null | undefined,
): Promise<CagesTrackDraftWithTippedTimes> {
  const validRowCount = tippedTimeRows.filter(
    (row) => row.tipped_hour !== null && row.tipped_hour !== undefined && row.total_cages > 0,
  ).length

  if (validRowCount === 0) {
    throw new CagesTippedTimeRequiredError()
  }

  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE cages_track_record
     SET cages_track_number = ?,
         date = ?,
         tippler_start_time = ?,
         tippler_stop_time = ?,
         cages_out = ?,
         cages_tipped = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'saved',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.cages_track_number || null,
      headerData.date || null,
      headerData.tippler_start_time || null,
      headerData.tippler_stop_time || null,
      headerData.cages_out,
      headerData.cages_tipped,
      headerData.note || null,
      checkedBy,
      acknowledgedBy,
      timestamp,
      recordId,
    ],
  )

  await applyTippedTimeRowChanges(recordId, tippedTimeRows, tippedTimeIdsToDelete, timestamp)

  const saved = await getDraftWithTippedTimes(recordId)

  if (!saved) {
    throw new Error('Gagal memuat ulang data cages track setelah disimpan.')
  }

  return saved
}

/**
 * screen-012--form-cages-track (entity-catalog v3) business_logic step 12
 * — 'Pause'. UPDATEs every header field on the given record (same column
 * set as `saveDraft()`) as-is, then sets status='draft_paused'/
 * updated_at=now — NO required-field validation, a checkpoint save (same
 * split as gradingRecordRepo.ts's `saveDraft()` vs
 * `pauseDraftWithFormData()`). Deliberately does NOT special-case
 * `tippler_stop_time` in any way — it is written exactly as given in
 * `headerData.tippler_stop_time`, which FormCagesTrackView.vue never
 * mutates during Pause (stays blank on a fresh draft, or whatever was
 * already stored), satisfying the "explicitly NOT touched/frozen on
 * Pause" business rule. Applies the same `checked_by`/`acknowledged_by`
 * role-stripping as `saveDraft()` (defense-in-depth — Form Cages Track's
 * tech spec role-gates both fields at all times, not only at Simpan), and
 * `tippedTimeRows`'s upserts / `tippedTimeIdsToDelete`'s deletes via
 * `applyTippedTimeRowChanges()`, identical semantics to `saveDraft()` but
 * with no gate before it runs.
 *
 * Returns the freshly paused draft (re-read via getDraftWithTippedTimes()),
 * same pattern as `saveDraft()`.
 */
export async function pauseDraftWithFormData(
  recordId: string,
  headerData: CagesTrackHeaderFormData,
  tippedTimeRows: CagesTippedTimeFormRow[],
  tippedTimeIdsToDelete: string[],
  currentUserRole: CagesTrackActorRole | null | undefined,
): Promise<CagesTrackDraftWithTippedTimes> {
  const timestamp = nowIso()
  const isSupervisor = currentUserRole === 'supervisor'
  const checkedBy = isSupervisor && headerData.checked_by ? headerData.checked_by : null
  const isMillManagement = currentUserRole === 'mill_management'
  const acknowledgedBy = isMillManagement && headerData.acknowledged_by ? headerData.acknowledged_by : null

  await run(
    `UPDATE cages_track_record
     SET cages_track_number = ?,
         date = ?,
         tippler_start_time = ?,
         tippler_stop_time = ?,
         cages_out = ?,
         cages_tipped = ?,
         note = ?,
         checked_by = ?,
         acknowledged_by = ?,
         status = 'draft_paused',
         updated_at = ?
     WHERE id = ?`,
    [
      headerData.cages_track_number || null,
      headerData.date || null,
      headerData.tippler_start_time || null,
      headerData.tippler_stop_time || null,
      headerData.cages_out,
      headerData.cages_tipped,
      headerData.note || null,
      checkedBy,
      acknowledgedBy,
      timestamp,
      recordId,
    ],
  )

  await applyTippedTimeRowChanges(recordId, tippedTimeRows, tippedTimeIdsToDelete, timestamp)

  const saved = await getDraftWithTippedTimes(recordId)

  if (!saved) {
    throw new Error('Gagal menyimpan progres cages track setelah pause.')
  }

  return saved
}

export const cagesTrackRecordRepo = {
  getProgressSummary,
  getTodaySummary,
  getDrafts,
  getAllRecords,
  createDraft,
  resumeDraft,
  pauseDraft,
  deleteDraft,
  getDraftWithTippedTimes,
  saveDraft,
  pauseDraftWithFormData,
}

export default cagesTrackRecordRepo

// Re-exported for readability at call sites that only need the status enum.
export const CAGES_TRACK_DRAFT_STATUSES = DRAFT_STATUSES
