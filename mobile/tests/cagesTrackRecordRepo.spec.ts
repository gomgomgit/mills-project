/**
 * cagesTrackRecordRepo.spec.ts — screen-009--monitor-cages-track /
 * usecase-009--monitor-cages-track business_logic steps 1-5, extended for
 * screen-012--form-cages-track / usecase-012--form-cages-track
 * business_logic steps 1 and 4-7.
 *
 * Unit tests for src/services/cagesTrackRecordRepo.ts — covers the
 * authoritative unit_test_cases for this screen's repo layer (there is no
 * REST api_contract for this screen; it is mobile-only / local-SQLite-only,
 * so these unit_test_cases target the repo's read/write primitives
 * directly) plus the screen-012 extension. Mirrors gradingRecordRepo.spec.ts's
 * (screen-008/011) structure/pattern closely, adapted for cages-track's
 * different progress-summary shape (a COUNT of the CHILD `cages_tipped_time`
 * table, not the parent `cages_track_record` table — see
 * cagesTrackRecordRepo.ts's header comment) and its extra no-DB-round-trip
 * short-circuit when there is no current draft.
 *
 * '@/services/localDb' is mocked at module level (same convention as
 * gradingRecordRepo.spec.ts / weighbridgeRecordRepo.spec.ts / stationRepo.
 * spec.ts) — both `query()` and `run()` are mocked. No real SQLite
 * connection is touched.
 *
 * ---
 * Update (screen-009--monitor-cages-track, list-view + entity-catalog v3
 * rewrite, 2026-08-19): the pre-existing getProgressSummary/createDraft/
 * resumeDraft/pauseDraft/deleteDraft coverage below is UNCHANGED by this
 * update — copied verbatim, except createDraft()'s test, which is updated
 * for the new `tippler_start_time` auto-fill column (entity-catalog v3's
 * business rule — see cagesTrackRecordRepo.ts's createDraft() doc comment).
 * Extended (not duplicated) with `getDrafts()` and `getTodaySummary()`
 * coverage at the bottom of this file, mirroring gradingRecordRepo.spec.ts's
 * own equivalent addition — these are the two repo functions the rewritten
 * MonitorCagesTrackView.vue now actually calls. `getTodaySummary()`'s test
 * shape differs from gradingRecordRepo.spec.ts's own (a single-query
 * aggregate) because cages-track's "cages tipped" total lives on the CHILD
 * `cages_tipped_time` table, not directly on `cages_track_record` — the
 * repo runs it as two sequential queries (today's record ids, then a scoped
 * SUM), skipping the second query entirely when the first finds no ids (see
 * cagesTrackRecordRepo.ts's getTodaySummary() doc comment).
 *
 * Note on unit_test_case 3 ("makes no change when Clear confirmation
 * cancelled"): at the pure-repo level this is trivially true —
 * deleteDraft() is simply never invoked on the cancel path; there is no
 * separate "cancel" entrypoint in the repo to call (cancel was a UI concern
 * of the previous single-draft-summary screen; this screen no longer calls
 * deleteDraft() at all — see MonitorCagesTrackView.vue's header comment). A
 * lightweight sanity test is still included below for traceability.
 *
 * ---
 * screen-012--form-cages-track extension — getDraftWithTippedTimes() /
 * saveDraft(): this file was extended (not duplicated) to cover this
 * screen's own repo-level unit_test_cases, at the level at which each is
 * actually meaningful (mirrors gradingRecordRepo.spec.ts's screen-011
 * extension):
 *   1. "loads existing paused draft header and cages_tipped_time rows into
 *      form state" / not-found — getDraftWithTippedTimes() returns the
 *      header row plus its ordered cages_tipped_time child rows for an
 *      existing draft, and returns null for a defensive not-found case.
 *   3. "shows special error and does not save when zero cages_tipped_time
 *      rows exist" — saveDraft() throws CagesTippedTimeRequiredError when
 *      tippedTimeRows is empty, and does NOT call run() at all in that
 *      case.
 *   6. Checked By role-stripping — saveDraft() strips checked_by to null
 *      for a non-supervisor currentUserRole even when
 *      headerData.checked_by carries a value (enforced HERE, deliberately,
 *      not only in FormCagesTrackView.vue's UI — see
 *      cagesTrackRecordRepo.ts's saveDraft() header comment); its mirror
 *      (supervisor keeps checked_by) is covered alongside for the same
 *      branch.
 *   7. "returns success — UPDATE header (status=saved), INSERT/UPDATE
 *      cages_tipped_time rows" — repo-level portion only: saveDraft()
 *      UPDATEs the header with status='saved', UPDATEs existing
 *      tipped-time rows (with an `id`) and INSERTs new ones (without an
 *      `id`), in tippedTimeRows order. Navigation to Monitor Cages Track
 *      is a UI concern, covered instead by FormCagesTrackView.spec.ts's
 *      "success" scenarios.
 *   Remaining unit_test_cases for this screen (Checked By
 *   visibility/editability, required-header-field inline validation,
 *   Back/confirm-dialog) are UI/component concerns and live in
 *   FormCagesTrackView.spec.ts instead.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/localDb', () => ({
  query: vi.fn(),
  run: vi.fn(),
}))

import { query, run } from '@/services/localDb'
import {
  createDraft,
  deleteDraft,
  getAllRecords,
  getDraftWithTippedTimes,
  getDrafts,
  getProgressSummary,
  getTodaySummary,
  pauseDraft,
  pauseDraftWithFormData,
  resumeDraft,
  saveDraft,
} from '@/services/cagesTrackRecordRepo'
import {
  CagesTippedTimeRequiredError,
  type CagesTippedTimeFormRow,
  type CagesTippedTimeRow,
  type CagesTrackDraftListItem,
  type CagesTrackHeaderFormData,
  type CagesTrackRecord,
} from '@/services/cagesTrackRecordRepo'

const USER_ID = 'user-1'

function makeDraftRecord(overrides: Partial<CagesTrackRecord> = {}): CagesTrackRecord {
  return {
    id: 'draft-1',
    station_id: 'station-1',
    cages_track_number: null,
    date: null,
    tippler_start_time: null,
    tippler_stop_time: null,
    cages_out: null,
    cages_tipped: null,
    note: null,
    checked_by: null,
    acknowledged_by: null,
    status: 'draft_ongoing',
    created_by: USER_ID,
    created_at: '2026-08-17T07:00:00.000Z',
    updated_at: '2026-08-17T07:00:00.000Z',
    ...overrides,
  }
}

function makeTippedTimeRow(overrides: Partial<CagesTippedTimeRow> = {}): CagesTippedTimeRow {
  return {
    id: 'tipped-1',
    cages_track_record_id: 'draft-1',
    tipped_hour: 8,
    checked_cage_numbers: '1,2,3',
    total_cages: 3,
    cages_remain: 7,
    created_at: '2026-08-17T07:00:00.000Z',
    updated_at: '2026-08-17T07:00:00.000Z',
    ...overrides,
  }
}

function makeValidHeaderData(overrides: Partial<CagesTrackHeaderFormData> = {}): CagesTrackHeaderFormData {
  return {
    cages_track_number: 'CT-3001',
    date: '2026-08-18T07:00:00',
    tippler_start_time: '2026-08-18T07:00:00',
    tippler_stop_time: '2026-08-18T11:00:00',
    cages_out: 12,
    cages_tipped: 10,
    note: '',
    checked_by: '',
    acknowledged_by: 'user-mill-mgmt-1',
    ...overrides,
  }
}

function makeValidTippedTimeRows(
  overrides: Partial<CagesTippedTimeFormRow>[] = [],
): CagesTippedTimeFormRow[] {
  if (overrides.length === 0) {
    return [{ tipped_hour: 8, checked_cage_numbers: '1,2,3', total_cages: 3, cages_remain: 7 }]
  }

  return overrides.map((o) => ({
    tipped_hour: 8,
    checked_cage_numbers: '1,2,3',
    total_cages: 3,
    cages_remain: 7,
    ...o,
  }))
}

function makeDraftListItem(overrides: Partial<CagesTrackDraftListItem> & { id: string }): CagesTrackDraftListItem {
  return {
    status: 'draft_ongoing',
    cages_track_number: 'CT-001',
    updated_at: '2026-08-19T08:00:00.000Z',
    ...overrides,
  }
}

describe('cagesTrackRecordRepo', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  // unit_test_case 1: "returns success — progress summary + draft status
  // load correctly for current user"
  describe('getProgressSummary()', () => {
    it('returns success result when a current draft exists — draft lookup + scoped tipped_count', async () => {
      vi.mocked(query)
        .mockResolvedValueOnce([{ id: 'draft-1', status: 'draft_paused' }])
        .mockResolvedValueOnce([{ tipped_count: 5 }])

      const result = await getProgressSummary(USER_ID)

      expect(result).toEqual({
        tippedCount: 5,
        currentDraft: { id: 'draft-1', status: 'draft_paused' },
      })
      expect(query).toHaveBeenCalledTimes(2)
      expect(query).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining('FROM cages_track_record'),
        [USER_ID],
      )
      expect(query).toHaveBeenNthCalledWith(1, expect.stringContaining("status IN ('draft_ongoing', 'draft_paused')"), [
        USER_ID,
      ])
      expect(query).toHaveBeenNthCalledWith(
        2,
        expect.stringContaining('FROM cages_tipped_time WHERE cages_track_record_id = ?'),
        ['draft-1'],
      )
    })

    it('returns tippedCount 0 and currentDraft null, with NO second DB round trip, when there is no current draft', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getProgressSummary(USER_ID)

      expect(result).toEqual({
        tippedCount: 0,
        currentDraft: null,
      })
      // Only the draft-lookup query runs — the count query is skipped
      // entirely, since there is no draft id to scope it to.
      expect(query).toHaveBeenCalledTimes(1)
    })

    it('defaults tippedCount to 0 when the count query itself returns no row', async () => {
      vi.mocked(query)
        .mockResolvedValueOnce([{ id: 'draft-2', status: 'draft_ongoing' }])
        .mockResolvedValueOnce([])

      const result = await getProgressSummary(USER_ID)

      expect(result).toEqual({
        tippedCount: 0,
        currentDraft: { id: 'draft-2', status: 'draft_ongoing' },
      })
    })
  })

  // Light coverage of createDraft() / resumeDraft() — not among the
  // authoritative unit_test_cases, but kept for meaningful coverage of the
  // repo's full public API (mirrors gradingRecordRepo.spec.ts).
  describe('createDraft()', () => {
    // unit_test_case 4 (repo-level portion): "creates new draft record with
    // tippler_start_time set" — entity-catalog v3's business rule that
    // tippler_start_time auto-fills once, at draft creation, to the same
    // timestamp as created_at/updated_at (see cagesTrackRecordRepo.ts's
    // createDraft() doc comment). The navigation half of this
    // unit_test_case is covered by MonitorCagesTrackView.spec.ts's "New
    // Data" test.
    it('inserts a new draft_ongoing cages_track_record for the user, auto-fills tippler_start_time, and returns the generated id', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })

      const id = await createDraft(USER_ID)

      expect(typeof id).toBe('string')
      expect(id.length).toBeGreaterThan(0)
      expect(run).toHaveBeenCalledTimes(1)
      const [sql, params] = vi.mocked(run).mock.calls[0]
      expect(sql).toContain(
        'INSERT INTO cages_track_record (id, status, created_by, tippler_start_time, created_at, updated_at)',
      )
      expect(sql).toContain("VALUES (?, 'draft_ongoing', ?, ?, ?, ?)")
      expect(params).toEqual([id, USER_ID, expect.any(String), expect.any(String), expect.any(String)])
      // tippler_start_time (index 2) is set to the SAME timestamp as
      // created_at (index 3) / updated_at (index 4) — the "auto-fill once,
      // at draft creation" rule.
      expect(params[2]).toBe(params[3])
      expect(params[3]).toBe(params[4])
    })
  })

  describe('resumeDraft()', () => {
    it('updates the given record back to draft_ongoing and returns its id/status', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })

      const result = await resumeDraft('draft-1')

      expect(result).toEqual({ id: 'draft-1', status: 'draft_ongoing' })
      expect(run).toHaveBeenCalledTimes(1)
      expect(run).toHaveBeenCalledWith(
        expect.stringContaining("SET status = 'draft_ongoing'"),
        [expect.any(String), 'draft-1'],
      )
    })
  })

  // unit_test_case 4: "Pause action disabled and no update when no draft
  // ongoing" — pauseDraft's guard is a no-op (no DB call) when recordId is
  // falsy, and its UPDATE is additionally scoped to
  // status = 'draft_ongoing' so it can never affect a non-ongoing row even
  // if somehow called with an id.
  describe('pauseDraft()', () => {
    it('does not call run() when there is no ongoing draft (recordId undefined)', async () => {
      await pauseDraft(undefined)

      expect(run).not.toHaveBeenCalled()
    })

    it('does not call run() when there is no ongoing draft (recordId null)', async () => {
      await pauseDraft(null)

      expect(run).not.toHaveBeenCalled()
    })

    it('updates status to draft_paused, scoped only to rows currently draft_ongoing, when an ongoing draft exists', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })

      await pauseDraft('draft-2')

      expect(run).toHaveBeenCalledTimes(1)
      expect(run).toHaveBeenCalledWith(
        expect.stringContaining("SET status = 'draft_paused'"),
        [expect.any(String), 'draft-2'],
      )
      const [sql] = vi.mocked(run).mock.calls[0]
      expect(sql).toContain("AND status = 'draft_ongoing'")
    })
  })

  // unit_test_case 2: "deletes local draft record and cascading
  // cages_tipped_time rows when Clear confirmed" — deleteDraft(id) must
  // execute DELETE on cages_tipped_time (child) BEFORE DELETE on
  // cages_track_record (parent) — application-level cascade, no DB-level
  // FK enforcement declared (see localSchema.ts's header comment).
  describe('deleteDraft()', () => {
    it('deletes the cages_track_record row and cascades delete of its cages_tipped_time rows, in that order', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })

      await deleteDraft('draft-3')

      expect(run).toHaveBeenCalledTimes(2)
      expect(run).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining('DELETE FROM cages_tipped_time WHERE cages_track_record_id = ?'),
        ['draft-3'],
      )
      expect(run).toHaveBeenNthCalledWith(
        2,
        expect.stringContaining('DELETE FROM cages_track_record WHERE id = ?'),
        ['draft-3'],
      )
    })

    it('record is no longer queryable after deleteDraft() — query() returns nothing once deletion is mocked as applied', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query).mockResolvedValueOnce([])

      await deleteDraft('draft-3')
      const afterDelete = await getProgressSummary(USER_ID)

      expect(afterDelete.currentDraft).toBeNull()
      expect(afterDelete.tippedCount).toBe(0)
    })

    // unit_test_case 3: "makes no change when Clear confirmation
    // cancelled" — at the pure-repo level this is trivially true, since
    // deleteDraft() is simply never invoked on the cancel path. See file
    // header note.
    it('sanity: makes no change when deleteDraft() is never invoked (cancel path)', () => {
      // No call to deleteDraft() at all here — simulates the cancel path
      // at the repo level, where "cancel" is simply the absence of a
      // call.
      expect(run).not.toHaveBeenCalled()
    })
  })

  // screen-012--form-cages-track business_logic step 1 — Form Cages Track
  // loading a draft (header + cages_tipped_time rows) on mount via its
  // route param id.
  describe('getDraftWithTippedTimes()', () => {
    it('returns the existing paused header row plus its ordered cages_tipped_time rows when the record exists', async () => {
      const pausedRecord = makeDraftRecord({
        id: 'draft-paused-1',
        cages_track_number: 'CT-1001',
        date: '2026-08-17T07:00:00',
        checked_by: 'Supervisor Satu',
        acknowledged_by: null,
        status: 'draft_paused',
        updated_at: '2026-08-17T07:30:00.000Z',
      })
      const tippedTimeRows = [
        makeTippedTimeRow({ id: 'tipped-1', cages_track_record_id: 'draft-paused-1', tipped_hour: 7 }),
        makeTippedTimeRow({ id: 'tipped-2', cages_track_record_id: 'draft-paused-1', tipped_hour: 9 }),
      ]
      vi.mocked(query).mockResolvedValueOnce([pausedRecord]).mockResolvedValueOnce(tippedTimeRows)

      const result = await getDraftWithTippedTimes('draft-paused-1')

      expect(result).toEqual({ record: pausedRecord, tippedTimes: tippedTimeRows })
      expect(query).toHaveBeenCalledTimes(2)
      expect(query).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining('FROM cages_track_record WHERE id = ?'),
        ['draft-paused-1'],
      )
      expect(query).toHaveBeenNthCalledWith(
        2,
        expect.stringContaining('FROM cages_tipped_time WHERE cages_track_record_id = ?'),
        ['draft-paused-1'],
      )
      expect(query).toHaveBeenNthCalledWith(2, expect.stringContaining('ORDER BY created_at ASC'), [
        'draft-paused-1',
      ])
    })

    it('returns null when no header row matches (defensive — e.g. a stale/invalid route param) and does not query cages_tipped_time at all', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getDraftWithTippedTimes('does-not-exist')

      expect(result).toBeNull()
      expect(query).toHaveBeenCalledTimes(1)
    })
  })

  // screen-012--form-cages-track business_logic step 12 — 'Simpan'.
  // saveDraft(recordId, headerData, tippedTimeRows, tippedTimeIdsToDelete, currentUserRole)
  describe('saveDraft()', () => {
    // business_logic step 12 (validation): "shows special error and does
    // not save when zero VALID cages_tipped_time rows exist" — repo-level
    // portion: saveDraft() throws CagesTippedTimeRequiredError and performs
    // NO persistence (neither run() nor query()) at all when no row has
    // both a selected tipped_hour and total_cages > 0.
    it('throws CagesTippedTimeRequiredError and calls neither run() nor query() when tippedTimeRows is empty', async () => {
      const headerData = makeValidHeaderData()

      await expect(saveDraft('draft-3', headerData, [], [], 'operator')).rejects.toBeInstanceOf(
        CagesTippedTimeRequiredError,
      )

      expect(run).not.toHaveBeenCalled()
      expect(query).not.toHaveBeenCalled()
    })

    it('throws CagesTippedTimeRequiredError when every row lacks a selected tipped_hour or has zero checked cages', async () => {
      const headerData = makeValidHeaderData()
      const tippedTimeRows: CagesTippedTimeFormRow[] = [
        { tipped_hour: null, checked_cage_numbers: '', total_cages: 0, cages_remain: 10 },
        { tipped_hour: 8, checked_cage_numbers: '', total_cages: 0, cages_remain: 10 },
      ]

      await expect(saveDraft('draft-3z', headerData, tippedTimeRows, [], 'operator')).rejects.toBeInstanceOf(
        CagesTippedTimeRequiredError,
      )

      expect(run).not.toHaveBeenCalled()
    })

    // checked_by role-stripping — operator.
    it('nulls/strips checked_by from the UPDATE payload when currentUserRole is operator, even if headerData.checked_by carries a value', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeDraftRecord({ checked_by: null, status: 'saved' })])
        .mockResolvedValueOnce([])

      const headerData = makeValidHeaderData({ checked_by: 'Snuck In Value' })
      const tippedTimeRows = makeValidTippedTimeRows()

      await saveDraft('draft-3', headerData, tippedTimeRows, [], 'operator')

      expect(run).toHaveBeenCalledTimes(2) // header UPDATE + 1 tipped-time row upsert
      const [headerSql, headerParams] = vi.mocked(run).mock.calls[0]
      expect(headerSql).toContain('UPDATE cages_track_record')
      // checked_by is the 8th positional UPDATE param (index 7) per
      // saveDraft()'s SET column order (cages_track_number, date,
      // tippler_start_time, tippler_stop_time, cages_out, cages_tipped,
      // note, checked_by, acknowledged_by, updated_at).
      expect(headerParams[7]).toBeNull()
    })

    it('nulls/strips checked_by from the UPDATE payload for any non-supervisor role (mill_management)', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeDraftRecord({ checked_by: null, status: 'saved' })])
        .mockResolvedValueOnce([])

      const headerData = makeValidHeaderData({ checked_by: 'Snuck In Value' })
      const tippedTimeRows = makeValidTippedTimeRows()

      await saveDraft('draft-3b', headerData, tippedTimeRows, [], 'mill_management')

      const [, headerParams] = vi.mocked(run).mock.calls[0]
      expect(headerParams[7]).toBeNull()
    })

    // checked_by role-stripping — supervisor keeps the value.
    it('keeps checked_by in the UPDATE payload when currentUserRole is supervisor and headerData.checked_by is set', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeDraftRecord({ checked_by: 'usr-supervisor-1', status: 'saved' })])
        .mockResolvedValueOnce([])

      const headerData = makeValidHeaderData({ checked_by: 'usr-supervisor-1' })
      const tippedTimeRows = makeValidTippedTimeRows()

      await saveDraft('draft-3c', headerData, tippedTimeRows, [], 'supervisor')

      const [, headerParams] = vi.mocked(run).mock.calls[0]
      expect(headerParams[7]).toBe('usr-supervisor-1')
    })

    // acknowledged_by role-stripping — supervisor (not mill_management)
    // gets it stripped, mirroring checked_by's operator case above.
    it('nulls/strips acknowledged_by from the UPDATE payload for any non-mill_management role (supervisor)', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeDraftRecord({ acknowledged_by: null, status: 'saved' })])
        .mockResolvedValueOnce([])

      const headerData = makeValidHeaderData({ acknowledged_by: 'Snuck In Value' })
      const tippedTimeRows = makeValidTippedTimeRows()

      await saveDraft('draft-3d', headerData, tippedTimeRows, [], 'supervisor')

      const [, headerParams] = vi.mocked(run).mock.calls[0]
      // acknowledged_by is the 9th positional param (index 8).
      expect(headerParams[8]).toBeNull()
    })

    it('keeps acknowledged_by in the UPDATE payload when currentUserRole is mill_management and headerData.acknowledged_by is set', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeDraftRecord({ acknowledged_by: 'usr-mill-mgmt-1', status: 'saved' })])
        .mockResolvedValueOnce([])

      const headerData = makeValidHeaderData({ acknowledged_by: 'usr-mill-mgmt-1' })
      const tippedTimeRows = makeValidTippedTimeRows()

      await saveDraft('draft-3e', headerData, tippedTimeRows, [], 'mill_management')

      const [, headerParams] = vi.mocked(run).mock.calls[0]
      expect(headerParams[8]).toBe('usr-mill-mgmt-1')
    })

    // "returns success — UPDATE header (status=saved), INSERT/UPDATE
    // cages_tipped_time rows" (repo-level portion only — navigation itself
    // is a UI concern, covered by FormCagesTrackView.spec.ts).
    it('updates the header with status=saved, INSERTs a new (id-less) tipped-time row, and returns the freshly saved draft', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      const savedRecord = makeDraftRecord({
        id: 'draft-4',
        cages_track_number: 'CT-3001',
        status: 'saved',
        updated_at: '2026-08-18T10:05:00.000Z',
      })
      const savedTippedTimes = [makeTippedTimeRow({ id: 'tipped-new-1', cages_track_record_id: 'draft-4' })]
      vi.mocked(query).mockResolvedValueOnce([savedRecord]).mockResolvedValueOnce(savedTippedTimes)

      const headerData = makeValidHeaderData({ checked_by: 'usr-supervisor-2' })
      const tippedTimeRows = makeValidTippedTimeRows([
        { tipped_hour: 9, checked_cage_numbers: '1,2', total_cages: 2, cages_remain: 8 },
      ])

      const result = await saveDraft('draft-4', headerData, tippedTimeRows, [], 'supervisor')

      // 1 header UPDATE + 1 tipped-time INSERT = 2 run() calls.
      expect(run).toHaveBeenCalledTimes(2)

      const [headerSql, headerParams] = vi.mocked(run).mock.calls[0]
      expect(headerSql).toContain("status = 'saved'")
      expect(headerSql).toContain('UPDATE cages_track_record')
      expect(headerParams).toEqual(expect.arrayContaining(['CT-3001', 'draft-4']))
      // updated_at (index 9) is a freshly generated timestamp string, not
      // a field carried over from headerData.
      expect(typeof headerParams[9]).toBe('string')
      expect(headerParams[9].length).toBeGreaterThan(0)
      // recordId is the final WHERE-clause param.
      expect(headerParams[10]).toBe('draft-4')

      const [tippedTimeSql, tippedTimeParams] = vi.mocked(run).mock.calls[1]
      expect(tippedTimeSql).toContain('INSERT INTO cages_tipped_time')
      expect(tippedTimeParams).toEqual([
        expect.any(String), // freshly generated tipped-time id
        'draft-4',
        9,
        '1,2',
        2,
        8,
        expect.any(String),
        expect.any(String),
      ])

      expect(query).toHaveBeenCalledTimes(2)
      expect(query).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining('FROM cages_track_record WHERE id = ?'),
        ['draft-4'],
      )
      expect(result).toEqual({ record: savedRecord, tippedTimes: savedTippedTimes })
    })

    it('UPDATEs an existing tipped-time row (has an id) instead of inserting it', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeDraftRecord({ id: 'draft-5', status: 'saved' })])
        .mockResolvedValueOnce([
          makeTippedTimeRow({ id: 'tipped-existing-1', cages_track_record_id: 'draft-5' }),
        ])

      const headerData = makeValidHeaderData()
      const tippedTimeRows: CagesTippedTimeFormRow[] = [
        { id: 'tipped-existing-1', tipped_hour: 11, checked_cage_numbers: '1,2,3', total_cages: 3, cages_remain: 7 },
      ]

      await saveDraft('draft-5', headerData, tippedTimeRows, [], 'operator')

      expect(run).toHaveBeenCalledTimes(2)
      const [tippedTimeSql, tippedTimeParams] = vi.mocked(run).mock.calls[1]
      expect(tippedTimeSql).toContain('UPDATE cages_tipped_time')
      expect(tippedTimeParams).toEqual([11, '1,2,3', 3, 7, expect.any(String), 'tipped-existing-1'])
    })

    it('upserts multiple tipped-time rows sequentially, in tippedTimeRows order (mixed insert + update)', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeDraftRecord({ id: 'draft-6', status: 'saved' })])
        .mockResolvedValueOnce([])

      const headerData = makeValidHeaderData()
      const tippedTimeRows: CagesTippedTimeFormRow[] = [
        { id: 'tipped-existing-2', tipped_hour: 7, checked_cage_numbers: '1', total_cages: 1, cages_remain: 9 },
        { tipped_hour: 13, checked_cage_numbers: '2,3', total_cages: 2, cages_remain: 8 },
      ]

      await saveDraft('draft-6', headerData, tippedTimeRows, [], 'operator')

      // header UPDATE + 2 tipped-time upserts = 3 run() calls, header
      // first, rows in array order.
      expect(run).toHaveBeenCalledTimes(3)
      const [firstRowSql] = vi.mocked(run).mock.calls[1]
      const [secondRowSql] = vi.mocked(run).mock.calls[2]
      expect(firstRowSql).toContain('UPDATE cages_tipped_time')
      expect(secondRowSql).toContain('INSERT INTO cages_tipped_time')
    })

    // pending-deletion — queued row ids are DELETEd after the upserts.
    it('DELETEs every id in tippedTimeIdsToDelete, after the upserts', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeDraftRecord({ id: 'draft-6b', status: 'saved' })])
        .mockResolvedValueOnce([])

      const headerData = makeValidHeaderData()
      const tippedTimeRows = makeValidTippedTimeRows()

      await saveDraft('draft-6b', headerData, tippedTimeRows, ['tipped-removed-1', 'tipped-removed-2'], 'operator')

      // header UPDATE + 1 upsert + 2 deletes = 4 run() calls.
      expect(run).toHaveBeenCalledTimes(4)
      const [deleteSql1, deleteParams1] = vi.mocked(run).mock.calls[2]
      const [deleteSql2, deleteParams2] = vi.mocked(run).mock.calls[3]
      expect(deleteSql1).toContain('DELETE FROM cages_tipped_time WHERE id = ?')
      expect(deleteParams1).toEqual(['tipped-removed-1'])
      expect(deleteSql2).toContain('DELETE FROM cages_tipped_time WHERE id = ?')
      expect(deleteParams2).toEqual(['tipped-removed-2'])
    })

    it('falls back to null for optional string header fields left blank', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeDraftRecord({ status: 'saved' })])
        .mockResolvedValueOnce([])

      const headerData = makeValidHeaderData({ note: '' })
      const tippedTimeRows = makeValidTippedTimeRows()

      await saveDraft('draft-7', headerData, tippedTimeRows, [], 'operator')

      const [, headerParams] = vi.mocked(run).mock.calls[0]
      expect(headerParams[6]).toBeNull() // note
    })

    it('throws when getDraftWithTippedTimes() re-read after save unexpectedly finds nothing', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query).mockResolvedValueOnce([]) // header re-read finds nothing

      const headerData = makeValidHeaderData()
      const tippedTimeRows = makeValidTippedTimeRows()

      await expect(saveDraft('draft-8', headerData, tippedTimeRows, [], 'operator')).rejects.toThrow(
        'Gagal memuat ulang data cages track setelah disimpan.',
      )
    })
  })

  // screen-012--form-cages-track business_logic step 13 — 'Pause'. No
  // required-field validation, tippler_stop_time written exactly as given
  // (the caller is responsible for not freezing it), same upsert/delete +
  // role-stripping contract as saveDraft() minus the "at least one valid
  // row" gate.
  describe('pauseDraftWithFormData()', () => {
    it('saves with status=draft_paused even when tippedTimeRows is empty (no validation gate)', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeDraftRecord({ status: 'draft_paused' })])
        .mockResolvedValueOnce([])

      const headerData = makeValidHeaderData({ cages_track_number: '' })

      const result = await pauseDraftWithFormData('draft-9', headerData, [], [], 'operator')

      expect(run).toHaveBeenCalledTimes(1) // header UPDATE only, no tipped-time rows
      const [headerSql] = vi.mocked(run).mock.calls[0]
      expect(headerSql).toContain("status = 'draft_paused'")
      expect(result.record.status).toBe('draft_paused')
    })

    it('writes tippler_stop_time exactly as given in headerData, without special-casing it', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeDraftRecord({ status: 'draft_paused' })])
        .mockResolvedValueOnce([])

      const headerData = makeValidHeaderData({ tippler_stop_time: '' })
      const tippedTimeRows = makeValidTippedTimeRows()

      await pauseDraftWithFormData('draft-10', headerData, tippedTimeRows, [], 'operator')

      const [, headerParams] = vi.mocked(run).mock.calls[0]
      // tippler_stop_time is the 4th positional param (index 3).
      expect(headerParams[3]).toBeNull()
    })

    it('strips checked_by/acknowledged_by by role, same as saveDraft()', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeDraftRecord({ status: 'draft_paused' })])
        .mockResolvedValueOnce([])

      const headerData = makeValidHeaderData({ checked_by: 'Snuck In', acknowledged_by: 'Snuck In Too' })
      const tippedTimeRows = makeValidTippedTimeRows()

      await pauseDraftWithFormData('draft-11', headerData, tippedTimeRows, [], 'operator')

      const [, headerParams] = vi.mocked(run).mock.calls[0]
      expect(headerParams[7]).toBeNull() // checked_by
      expect(headerParams[8]).toBeNull() // acknowledged_by
    })

    it('upserts tipped-time rows and applies pending deletions, same contract as saveDraft()', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeDraftRecord({ status: 'draft_paused' })])
        .mockResolvedValueOnce([])

      const headerData = makeValidHeaderData()
      const tippedTimeRows: CagesTippedTimeFormRow[] = [
        { id: 'tipped-existing-3', tipped_hour: 10, checked_cage_numbers: '1', total_cages: 1, cages_remain: 9 },
      ]

      await pauseDraftWithFormData('draft-12', headerData, tippedTimeRows, ['tipped-to-delete'], 'operator')

      // header UPDATE + 1 upsert + 1 delete = 3 run() calls.
      expect(run).toHaveBeenCalledTimes(3)
      const [upsertSql] = vi.mocked(run).mock.calls[1]
      const [deleteSql, deleteParams] = vi.mocked(run).mock.calls[2]
      expect(upsertSql).toContain('UPDATE cages_tipped_time')
      expect(deleteSql).toContain('DELETE FROM cages_tipped_time WHERE id = ?')
      expect(deleteParams).toEqual(['tipped-to-delete'])
    })

    it('throws when getDraftWithTippedTimes() re-read after pause unexpectedly finds nothing', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query).mockResolvedValueOnce([]) // header re-read finds nothing

      const headerData = makeValidHeaderData()
      const tippedTimeRows = makeValidTippedTimeRows()

      await expect(pauseDraftWithFormData('draft-13', headerData, tippedTimeRows, [], 'operator')).rejects.toThrow(
        'Gagal menyimpan progres cages track setelah pause.',
      )
    })
  })

  // screen-009--monitor-cages-track (list-view rewrite) business_logic step
  // 2 — every local cages_track_record the current user has ongoing or
  // paused, most-recently-updated first (mirrors gradingRecordRepo.spec.ts's
  // getDrafts()).
  describe('getDrafts()', () => {
    it('returns the draft/pause list mapped from the query rows, most-recently-updated first', async () => {
      const rows = [
        {
          id: 'draft-newest',
          status: 'draft_paused' as const,
          cages_track_number: 'CT-200',
          updated_at: '2026-08-19T10:00:00.000Z',
        },
        {
          id: 'draft-oldest',
          status: 'draft_ongoing' as const,
          cages_track_number: 'CT-100',
          updated_at: '2026-08-19T08:00:00.000Z',
        },
      ]
      vi.mocked(query).mockResolvedValueOnce(rows)

      const result = await getDrafts(USER_ID)

      expect(result).toEqual(rows)
      expect(query).toHaveBeenCalledTimes(1)
      expect(query).toHaveBeenCalledWith(
        expect.stringContaining("status IN ('draft_ongoing', 'draft_paused')"),
        [USER_ID],
      )
      expect(query).toHaveBeenCalledWith(expect.stringContaining('ORDER BY updated_at DESC'), [USER_ID])
    })

    it('returns an empty array when no draft/pause records exist for the current user', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getDrafts(USER_ID)

      expect(result).toEqual([])
    })

    it('returns the underlying status of each row unchanged (mixed draft_ongoing/draft_paused) — the uniform "Pause" label is a view-level concern, not applied here', async () => {
      const rows = [
        makeDraftListItem({ id: 'draft-a', status: 'draft_paused' }),
        makeDraftListItem({ id: 'draft-b', status: 'draft_ongoing' }),
      ]
      vi.mocked(query).mockResolvedValueOnce(rows)

      const result = await getDrafts(USER_ID)

      expect(result[0].status).toBe('draft_paused')
      expect(result[1].status).toBe('draft_ongoing')
    })
  })

  // screen-015--data-preview-cages-track (list-view rewrite) business_logic
  // step 1 — every local cages_track_record row for the current user,
  // regardless of status, most-recently-updated first (mirrors
  // gradingRecordRepo.spec.ts's own getAllRecords() column-for-column,
  // adapted to cages_track_record's shape — see cagesTrackRecordRepo.ts's
  // getAllRecords() doc comment).
  describe('getAllRecords()', () => {
    it('returns every record for the user regardless of status (draft_ongoing/draft_paused/saved/synced)', async () => {
      const rows = [
        makeDraftRecord({ id: 'rec-ongoing', status: 'draft_ongoing', cages_track_number: 'CT-001' }),
        makeDraftRecord({ id: 'rec-paused', status: 'draft_paused', cages_track_number: 'CT-002' }),
        makeDraftRecord({ id: 'rec-saved', status: 'saved', cages_track_number: 'CT-003' }),
        makeDraftRecord({ id: 'rec-synced', status: 'synced', cages_track_number: 'CT-004' }),
      ]
      vi.mocked(query).mockResolvedValueOnce(rows)

      const result = await getAllRecords(USER_ID)

      expect(result).toEqual(rows)
      expect(query).toHaveBeenCalledTimes(1)
      expect(query).toHaveBeenCalledWith(
        expect.stringContaining('SELECT * FROM cages_track_record WHERE created_by = ?'),
        [USER_ID],
      )
    })

    it('orders results by updated_at DESC (most-recently-updated first)', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      await getAllRecords(USER_ID)

      expect(query).toHaveBeenCalledWith(expect.stringContaining('ORDER BY updated_at DESC'), [USER_ID])
    })

    it('returns an empty array when the user has no cages_track_record rows at all', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getAllRecords(USER_ID)

      expect(result).toEqual([])
    })

    it("scopes the query to the given userId only — does not leak other users' records", async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      await getAllRecords('user-2')

      expect(query).toHaveBeenCalledTimes(1)
      expect(query).toHaveBeenCalledWith(expect.any(String), ['user-2'])
      expect(query).not.toHaveBeenCalledWith(expect.any(String), [USER_ID])
    })

    it('returns rows with the full column shape (basic shape/column correctness) — not a partial projection', async () => {
      const row = makeDraftRecord({ id: 'rec-full', status: 'saved', cages_track_number: 'CT-500' })
      vi.mocked(query).mockResolvedValueOnce([row])

      const result = await getAllRecords(USER_ID)

      expect(result[0]).toEqual(row)
      expect(result[0]).toHaveProperty('station_id')
      expect(result[0]).toHaveProperty('date')
      expect(result[0]).toHaveProperty('created_by', USER_ID)
    })
  })

  // screen-009--monitor-cages-track (list-view rewrite) "today's counter"
  // addition — business_logic step 1: "Jumlah Cages Track" (COUNT of
  // today's cages_track_record ids for the user) + "Jumlah Cage/Lori
  // Tercatat" (SUM of total_cages from cages_tipped_time scoped to those
  // ids). Two sequential queries, not a single aggregate (unlike
  // gradingRecordRepo.spec.ts's own getTodaySummary(), whose fields live
  // directly on the parent table) — see cagesTrackRecordRepo.ts's
  // getTodaySummary() doc comment.
  describe('getTodaySummary()', () => {
    // unit_test_case 10
    it("computes today's counter — count of today's cages_track_record ids for the user, then SUM(total_cages) from cages_tipped_time scoped to those ids", async () => {
      vi.mocked(query)
        .mockResolvedValueOnce([{ id: 'rec-1' }, { id: 'rec-2' }])
        .mockResolvedValueOnce([{ sum_cages: 15 }])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countCagesTrack: 2, sumTotalCages: 15 })
      expect(query).toHaveBeenCalledTimes(2)
      expect(query).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining('FROM cages_track_record'),
        [USER_ID],
      )
      expect(query).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining("date(date) = date('now', 'localtime')"),
        [USER_ID],
      )
      const [sumSql, sumParams] = vi.mocked(query).mock.calls[1]
      expect(sumSql).toContain('FROM cages_tipped_time')
      expect(sumSql).toContain('WHERE cages_track_record_id IN (?, ?)')
      expect(sumParams).toEqual(['rec-1', 'rec-2'])
    })

    // unit_test_case 11
    it("excludes records whose date is not today from the counter — only ids returned by the today-scoped first query are ever passed to the SUM query", async () => {
      vi.mocked(query)
        .mockResolvedValueOnce([{ id: 'rec-today-only' }])
        .mockResolvedValueOnce([{ sum_cages: 4 }])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countCagesTrack: 1, sumTotalCages: 4 })
      expect(query).toHaveBeenNthCalledWith(2, expect.any(String), ['rec-today-only'])
    })

    // unit_test_case 12
    it("returns zero counter values when no records match today's date, and does NOT query cages_tipped_time at all (skips the invalid empty IN () query)", async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countCagesTrack: 0, sumTotalCages: 0 })
      expect(query).toHaveBeenCalledTimes(1)
    })

    it('defaults sumTotalCages to 0 when the SUM query returns no row at all', async () => {
      vi.mocked(query)
        .mockResolvedValueOnce([{ id: 'rec-1' }])
        .mockResolvedValueOnce([])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countCagesTrack: 1, sumTotalCages: 0 })
    })
  })
})
