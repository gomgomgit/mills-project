/**
 * gradingRecordRepo.spec.ts — screen-008--monitor-grading /
 * usecase-008--monitor-grading business_logic steps 1-5, extended for
 * screen-011--form-grading / usecase-011--form-grading business_logic
 * steps 1 and 4-7 (entity-catalog v2 rewrite).
 *
 * Update (screen-008--monitor-grading, list-view + entity-catalog v2
 * rewrite, 2026-08-18): extended (not duplicated) with `getDrafts()` and
 * `getTodaySummary()` coverage at the bottom of this file, mirroring
 * weighbridgeRecordRepo.spec.ts's own "today's counter"/list-view
 * addition — these are the two repo functions the rewritten
 * MonitorGradingView.vue now actually calls (`getProgressSummary()`
 * below is kept for its own continued coverage — it is still exported
 * and used by StationListView.vue's draft-status-by-type lookup, even
 * though MonitorGradingView.vue itself no longer calls it). This whole
 * screen-008 block (getProgressSummary/createDraft/resumeDraft/
 * pauseDraft/deleteDraft/getDrafts/getTodaySummary) is UNCHANGED by this
 * update — copied verbatim from the pre-existing file.
 *
 * Update (screen-011--form-grading, entity-catalog v2 rewrite,
 * 2026-08-19): the screen-011--form-grading block below (previously
 * legacy/pre-v2 coverage — GradingRecord's vehicle_number/driver_name/
 * block, GradingDetailRow's free-text `category`, no WB Card No /
 * Quality Parameter reference lists, no Pause path — that never matched
 * localSchema.ts's authoritative v2 CREATE_GRADING_RECORD/
 * CREATE_GRADING_DETAIL/CREATE_GRADING_PARAMETER shape) is now fully
 * REPLACED to match gradingRecordRepo.ts's own v2 rewrite:
 *   - `getDraftWithDetails()` — header + detail rows load, not-found null
 *     branch.
 *   - `getWeighbridgeRecordOptions()` — every local weighbridge_record
 *     row, any status, ordered by record_datetime DESC, NOT scoped to
 *     created_by (new).
 *   - `getGradingParameterOptions()` — every grading_parameter row,
 *     ordered by sort_order (new).
 *   - `saveDraft()` (Simpan) — the required-detail-row gate
 *     (GradingDetailRequiredError, thrown before any write when zero
 *     rows have both grading_parameter_id AND a non-null/undefined
 *     quantity), and the upsert (UPDATE existing/INSERT new)/delete
 *     semantics for detail rows.
 *   - `pauseDraftWithFormData()` (Pause, new) — identical upsert/delete
 *     semantics, but NO validation gate at all (checkpoint save).
 *
 * IMPORTANT — repo-level scope of saveDraft()'s validation: per
 * gradingRecordRepo.ts's own saveDraft() doc comment, required-HEADER-
 * field validation (Grading No/WB Card No/Vehicle Code/Estate/Netto/
 * Quantity) is explicitly the CALLER's (FormGradingView.vue's) job — the
 * repo function assumes the header has already been validated and never
 * itself rejects a save for a missing header field (mirrors
 * weighbridgeRecordRepo.ts's saveDraft(), which has the same split).
 * Only the detail-row gate (GradingDetailRequiredError) is enforced at
 * this repo level. So — unlike this repo file's detail-row-gate
 * coverage below — required-header-field-missing rejection is NOT
 * exercised here; it is covered instead at the component level, in
 * FormGradingView.spec.ts, which is where that inline per-field
 * validation actually lives.
 *
 * '@/services/localDb' is mocked at module level (same convention as
 * weighbridgeRecordRepo.spec.ts / stationRepo.spec.ts) — both `query()`
 * and `run()` are mocked. No real SQLite connection is touched.
 *
 * Note on unit_test_case 3 ("keeps draft unchanged when Clear cancelled"):
 * at the pure-repo level this is trivially true (deleteDraft() is simply
 * never invoked on the cancel path — there is no separate "cancel"
 * entrypoint in the repo to call). A lightweight sanity test is still
 * included below for traceability (mirrors
 * weighbridgeRecordRepo.spec.ts's equivalent), but the meaningful,
 * non-trivial assertion of this intent — that clicking Clear then Cancel
 * via ConfirmDialog does NOT call gradingRecordRepo's deleteDraft() — lives
 * in MonitorGradingView.spec.ts's scenario 6 ("Clear dibatalkan tanpa
 * konfirmasi"), which is the level at which "cancel" actually exists as a
 * user action.
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
  getDraftWithDetails,
  getDrafts,
  getGradingParameterOptions,
  getProgressSummary,
  getTodaySummary,
  getWeighbridgeRecordOptions,
  pauseDraft,
  pauseDraftWithFormData,
  resumeDraft,
  saveDraft,
} from '@/services/gradingRecordRepo'
import {
  GradingDetailRequiredError,
  type GradingDetailFormRow,
  type GradingDetailRow,
  type GradingDraftListItem,
  type GradingHeaderFormData,
  type GradingParameterOption,
  type GradingRecord,
  type WeighbridgeRecordOption,
} from '@/services/gradingRecordRepo'

const USER_ID = 'user-1'

// screen-011--form-grading (entity-catalog v2) — mirrors
// gradingRecordRepo.ts's GradingRecord column-for-column.
function makeDraftRecord(overrides: Partial<GradingRecord> = {}): GradingRecord {
  return {
    id: 'draft-1',
    station_id: 'station-1',
    grading_number: null,
    date: null,
    weighbridge_record_id: null,
    license_plate_no: null,
    vehicle_code: null,
    estate_supplier: null,
    division: null,
    netto: null,
    quantity: null,
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

function makeDraftListItem(overrides: Partial<GradingDraftListItem> & { id: string }): GradingDraftListItem {
  return {
    status: 'draft_ongoing',
    grading_number: 'GR-001',
    updated_at: '2026-08-18T08:00:00.000Z',
    ...overrides,
  }
}

// screen-011--form-grading (entity-catalog v2) — mirrors
// gradingRecordRepo.ts's GradingDetailRow column-for-column.
function makeDetailRow(overrides: Partial<GradingDetailRow> = {}): GradingDetailRow {
  return {
    id: 'detail-1',
    grading_record_id: 'draft-1',
    grading_parameter_id: 'param-1',
    quantity: 10,
    uom: 'kg',
    percentage: 25,
    created_at: '2026-08-17T07:00:00.000Z',
    updated_at: '2026-08-17T07:00:00.000Z',
    ...overrides,
  }
}

// screen-011--form-grading (entity-catalog v2) — mirrors
// gradingRecordRepo.ts's GradingHeaderFormData column-for-column (no
// checked_by/acknowledged_by — Form Grading collects neither, see
// gradingRecordRepo.ts's header comment).
function makeValidHeaderData(overrides: Partial<GradingHeaderFormData> = {}): GradingHeaderFormData {
  return {
    grading_number: 'GR-3001',
    date: '2026-08-18T08:00:00',
    weighbridge_record_id: 'wb-1',
    license_plate_no: 'B 5678 EF',
    vehicle_code: 'VC-001',
    estate_supplier: 'Estate B',
    division: 'Divisi 2',
    netto: 15000,
    quantity: 12,
    note: 'Catatan uji',
    ...overrides,
  }
}

function makeValidDetailRows(overrides: Partial<GradingDetailFormRow>[] = []): GradingDetailFormRow[] {
  if (overrides.length === 0) {
    return [{ grading_parameter_id: 'param-1', quantity: 5, uom: 'kg', percentage: 33.33 }]
  }

  return overrides.map((o) => ({ grading_parameter_id: 'param-1', quantity: 5, uom: 'kg', percentage: null, ...o }))
}

describe('gradingRecordRepo', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  // unit_test_case 1 (part A): "returns success — progress summary + draft
  // status load correctly"
  describe('getProgressSummary()', () => {
    it('returns success result when all conditions pass — record count + current draft', async () => {
      vi.mocked(query)
        .mockResolvedValueOnce([{ record_count: 3 }])
        .mockResolvedValueOnce([{ id: 'draft-1', status: 'draft_paused' }])

      const result = await getProgressSummary(USER_ID)

      expect(result).toEqual({
        recordCount: 3,
        currentDraft: { id: 'draft-1', status: 'draft_paused' },
      })
      expect(query).toHaveBeenCalledTimes(2)
      expect(query).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining('FROM grading_record'),
        [USER_ID],
      )
      expect(query).toHaveBeenNthCalledWith(
        2,
        expect.stringContaining("status IN ('draft_ongoing', 'draft_paused')"),
        [USER_ID],
      )
    })

    it('returns recordCount 0 and currentDraft null when there are no rows / no draft', async () => {
      vi.mocked(query)
        .mockResolvedValueOnce([{ record_count: 0 }])
        .mockResolvedValueOnce([])

      const result = await getProgressSummary(USER_ID)

      expect(result).toEqual({
        recordCount: 0,
        currentDraft: null,
      })
    })
  })

  // unit_test_case 1 (part B): "Mulai Input Baru inserts new grading_record
  // status=draft_ongoing created_by=current user, navigates to Form
  // Grading with new draft id"
  describe('createDraft()', () => {
    it('inserts a new draft_ongoing record for the user and returns the generated id', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })

      const id = await createDraft(USER_ID)

      expect(typeof id).toBe('string')
      expect(id.length).toBeGreaterThan(0)
      expect(run).toHaveBeenCalledTimes(1)
      expect(run).toHaveBeenCalledWith(
        expect.stringContaining('INSERT INTO grading_record'),
        [id, USER_ID, expect.any(String), expect.any(String)],
      )
    })

    it('always inserts with status draft_ongoing regardless of caller', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })

      await createDraft(USER_ID)

      const [sql] = vi.mocked(run).mock.calls[0]
      expect(sql).toContain("VALUES (?, 'draft_ongoing', ?, ?, ?)")
    })
  })

  // Light coverage of resumeDraft() — not one of the 4 authoritative
  // unit_test_cases, but necessary for meaningful coverage of the repo's
  // full public API (mirrors weighbridgeRecordRepo.spec.ts).
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

  // unit_test_case 4: "does not trigger Pause when no ongoing draft
  // exists" — pauseDraft's guard is a no-op (no DB call) when recordId is
  // falsy, and its UPDATE is additionally scoped to
  // status = 'draft_ongoing' so it can never affect a non-ongoing row even
  // if called with an id.
  describe('pauseDraft()', () => {
    it('does not trigger Pause / call run() when no ongoing draft exists (recordId undefined)', async () => {
      await pauseDraft(undefined)

      expect(run).not.toHaveBeenCalled()
    })

    it('does not trigger Pause / call run() when no ongoing draft exists (recordId null)', async () => {
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

  // unit_test_case 2: "deletes local draft record when Clear confirmed" —
  // deleteDraft(id) must execute DELETE on grading_record AND cascade
  // DELETE on grading_detail rows for that grading_record_id, in that
  // order (child before parent, application-level cascade — see
  // gradingRecordRepo.ts's header comment / localSchema.ts's note on no
  // DB-level FK enforcement).
  describe('deleteDraft()', () => {
    it('deletes local draft record when Clear confirmed — cascades grading_detail before deleting grading_record', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })

      await deleteDraft('draft-3')

      expect(run).toHaveBeenCalledTimes(2)
      expect(run).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining('DELETE FROM grading_detail WHERE grading_record_id = ?'),
        ['draft-3'],
      )
      expect(run).toHaveBeenNthCalledWith(
        2,
        expect.stringContaining('DELETE FROM grading_record WHERE id = ?'),
        ['draft-3'],
      )
    })

    it('record is no longer queryable after deleteDraft() — query() returns nothing once deletion is mocked as applied', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query).mockResolvedValueOnce([]).mockResolvedValueOnce([])

      await deleteDraft('draft-3')
      const afterDelete = await getProgressSummary(USER_ID)

      expect(afterDelete.currentDraft).toBeNull()
    })

    // unit_test_case 3: "keeps draft unchanged when Clear cancelled" — at
    // the pure-repo level this is trivially true, since deleteDraft() is
    // simply never invoked on the cancel path. See file header note.
    it('sanity: keeps grading_record/grading_detail unchanged when deleteDraft() is never invoked (cancel path) — see MonitorGradingView.spec.ts scenario 6 for the meaningful assertion', () => {
      // No call to deleteDraft() at all here — simulates the cancel path
      // at the repo level, where "cancel" is simply the absence of a
      // call.
      expect(run).not.toHaveBeenCalled()
    })
  })

  // screen-011--form-grading business_logic step 1 — Form Grading loading a
  // draft (header + grading_detail rows) on mount via its route param id.
  describe('getDraftWithDetails()', () => {
    it('loads header + detail rows correctly for an existing draft', async () => {
      const record = makeDraftRecord({
        id: 'draft-paused-1',
        status: 'draft_paused',
        grading_number: 'GR-1001',
        weighbridge_record_id: 'wb-1',
        netto: 15000,
        quantity: 12,
      })
      const details = [
        makeDetailRow({ id: 'detail-1', grading_record_id: 'draft-paused-1' }),
        makeDetailRow({
          id: 'detail-2',
          grading_record_id: 'draft-paused-1',
          grading_parameter_id: 'param-2',
          quantity: 7,
          uom: 'bunch',
          percentage: 58.33,
        }),
      ]
      vi.mocked(query).mockResolvedValueOnce([record]).mockResolvedValueOnce(details)

      const result = await getDraftWithDetails('draft-paused-1')

      expect(result).toEqual({ record, details })
      expect(query).toHaveBeenCalledTimes(2)
      expect(query).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining('FROM grading_record WHERE id = ?'),
        ['draft-paused-1'],
      )
      expect(query).toHaveBeenNthCalledWith(
        2,
        expect.stringContaining('FROM grading_detail WHERE grading_record_id = ?'),
        ['draft-paused-1'],
      )
      expect(query).toHaveBeenNthCalledWith(
        2,
        expect.stringContaining('ORDER BY created_at ASC'),
        ['draft-paused-1'],
      )
    })

    it('loads a freshly-created new draft (no detail rows yet) with null optional header fields', async () => {
      const newRecord = makeDraftRecord({ id: 'draft-new-1', status: 'draft_ongoing' })
      vi.mocked(query).mockResolvedValueOnce([newRecord]).mockResolvedValueOnce([])

      const result = await getDraftWithDetails('draft-new-1')

      expect(result).not.toBeNull()
      expect(result?.record.status).toBe('draft_ongoing')
      expect(result?.record.grading_number).toBeNull()
      expect(result?.record.weighbridge_record_id).toBeNull()
      expect(result?.details).toEqual([])
    })

    it('returns null when no header row matches (defensive — e.g. a stale/invalid route param)', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getDraftWithDetails('does-not-exist')

      expect(result).toBeNull()
      // Never queries grading_detail once the header lookup comes back
      // empty.
      expect(query).toHaveBeenCalledTimes(1)
    })
  })

  // screen-011--form-grading — FormGradingView.vue's "WB Card No" dropdown
  // reference list.
  describe('getWeighbridgeRecordOptions()', () => {
    it('returns ALL local weighbridge_record rows regardless of status, ordered by record_datetime DESC, NOT scoped to created_by', async () => {
      const rows: WeighbridgeRecordOption[] = [
        {
          id: 'wb-1',
          wb_card_number: 'WB-2001',
          record_datetime: '2026-08-18T09:00:00.000Z',
          vehicle_number: 'B 1234 AA',
          estate_supplier: 'Estate A',
          division: 'Divisi 1',
        },
        {
          id: 'wb-2',
          wb_card_number: 'WB-2002',
          record_datetime: '2026-08-17T09:00:00.000Z',
          vehicle_number: 'B 5678 BB',
          estate_supplier: 'Estate B',
          division: 'Divisi 2',
        },
      ]
      vi.mocked(query).mockResolvedValueOnce(rows)

      const result = await getWeighbridgeRecordOptions()

      expect(result).toEqual(rows)
      expect(query).toHaveBeenCalledTimes(1)
      const [sql, params] = vi.mocked(query).mock.calls[0]
      expect(sql).toContain('FROM weighbridge_record')
      expect(sql).toContain('ORDER BY record_datetime DESC')
      expect(sql).not.toContain('created_by')
      // Deliberately no WHERE param at all — every local row is returned,
      // regardless of which device user created it.
      expect(params).toBeUndefined()
    })

    it('returns an empty array (no crash) when there is no local weighbridge_record data at all', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getWeighbridgeRecordOptions()

      expect(result).toEqual([])
    })
  })

  // screen-011--form-grading — each Grading Detail row's "Quality
  // Parameter" dropdown reference list.
  describe('getGradingParameterOptions()', () => {
    it('returns all grading_parameter rows ordered by sort_order', async () => {
      const rows: GradingParameterOption[] = [
        { id: 'param-1', name: 'Fraksi Matang', uom: 'kg', sort_order: 1 },
        { id: 'param-2', name: 'Buah Mentah', uom: 'bunch', sort_order: 2 },
      ]
      vi.mocked(query).mockResolvedValueOnce(rows)

      const result = await getGradingParameterOptions()

      expect(result).toEqual(rows)
      expect(query).toHaveBeenCalledTimes(1)
      expect(query).toHaveBeenCalledWith(expect.stringContaining('FROM grading_parameter'))
      expect(query).toHaveBeenCalledWith(expect.stringContaining('ORDER BY sort_order ASC'))
    })

    it('returns an empty array when the grading_parameter master list is empty', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getGradingParameterOptions()

      expect(result).toEqual([])
    })
  })

  // screen-011--form-grading business_logic — 'Simpan'.
  describe('saveDraft()', () => {
    it('throws GradingDetailRequiredError and performs no write when detailRows is empty', async () => {
      await expect(saveDraft('draft-1', makeValidHeaderData(), [], [])).rejects.toBeInstanceOf(
        GradingDetailRequiredError,
      )

      expect(run).not.toHaveBeenCalled()
      expect(query).not.toHaveBeenCalled()
    })

    // Detail-row validity gate: a row only counts as "valid" when it has
    // BOTH a truthy grading_parameter_id AND a non-null/non-undefined
    // quantity.
    describe('detail row validity gate', () => {
      it('throws when every row is missing grading_parameter_id and/or quantity', async () => {
        const rows = makeValidDetailRows([
          { grading_parameter_id: '', quantity: 5 },
          { grading_parameter_id: 'param-1', quantity: null },
          { grading_parameter_id: 'param-2', quantity: undefined as unknown as number },
        ])

        await expect(saveDraft('draft-1', makeValidHeaderData(), rows, [])).rejects.toBeInstanceOf(
          GradingDetailRequiredError,
        )
        expect(run).not.toHaveBeenCalled()
      })

      it('treats a row with grading_parameter_id + quantity=0 as valid — 0 is not null/undefined, so it passes the gate', async () => {
        vi.mocked(run).mockResolvedValue({ changes: 1 })
        vi.mocked(query).mockResolvedValueOnce([makeDraftRecord({ status: 'saved' })]).mockResolvedValueOnce([])

        const rows = makeValidDetailRows([{ grading_parameter_id: 'param-1', quantity: 0 }])

        await expect(saveDraft('draft-1', makeValidHeaderData(), rows, [])).resolves.toBeDefined()
        expect(run).toHaveBeenCalled()
      })

      it('proceeds to save once at least one row is valid, even when other rows in the same array are incomplete', async () => {
        vi.mocked(run).mockResolvedValue({ changes: 1 })
        vi.mocked(query).mockResolvedValueOnce([makeDraftRecord({ status: 'saved' })]).mockResolvedValueOnce([])

        const rows: GradingDetailFormRow[] = [
          { grading_parameter_id: '', quantity: null, uom: '', percentage: null },
          { grading_parameter_id: 'param-1', quantity: 5, uom: 'kg', percentage: 33 },
        ]

        await saveDraft('draft-1', makeValidHeaderData(), rows, [])

        // Header UPDATE + 2 row upserts (both rows are still upserted —
        // the gate only decides whether the save proceeds at all, not
        // which individual rows are written; see gradingRecordRepo.ts's
        // saveDraft() doc comment).
        expect(run).toHaveBeenCalledTimes(3)
      })
    })

    it('updates the header with status=saved, upserts detail rows (INSERT new / UPDATE existing), deletes queued ids, and returns the freshly saved draft', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      const savedRecord = makeDraftRecord({ id: 'draft-2', status: 'saved', grading_number: 'GR-3001' })
      const savedDetails = [makeDetailRow({ id: 'detail-existing-1', grading_record_id: 'draft-2' })]
      vi.mocked(query).mockResolvedValueOnce([savedRecord]).mockResolvedValueOnce(savedDetails)

      const headerData = makeValidHeaderData()
      const detailRows: GradingDetailFormRow[] = [
        { id: 'detail-existing-1', grading_parameter_id: 'param-1', quantity: 5, uom: 'kg', percentage: 33.33 },
        { grading_parameter_id: 'param-2', quantity: 3, uom: 'bunch', percentage: 25 },
      ]

      const result = await saveDraft('draft-2', headerData, detailRows, ['detail-to-remove'])

      expect(run).toHaveBeenCalledTimes(4)

      // 1) header UPDATE — status='saved', column order per
      // saveDraft()'s SET clause.
      expect(run).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining("status = 'saved'"),
        [
          headerData.grading_number,
          headerData.date,
          headerData.weighbridge_record_id,
          headerData.license_plate_no,
          headerData.vehicle_code,
          headerData.estate_supplier,
          headerData.division,
          headerData.netto,
          headerData.quantity,
          headerData.note,
          expect.any(String),
          'draft-2',
        ],
      )

      // 2) row with an existing id -> UPDATE grading_detail.
      expect(run).toHaveBeenNthCalledWith(
        2,
        expect.stringContaining('UPDATE grading_detail'),
        ['param-1', 5, 'kg', 33.33, expect.any(String), 'detail-existing-1'],
      )

      // 3) row with no id -> INSERT grading_detail with a freshly
      // generated id.
      expect(run).toHaveBeenNthCalledWith(
        3,
        expect.stringContaining('INSERT INTO grading_detail'),
        [expect.any(String), 'draft-2', 'param-2', 3, 'bunch', 25, expect.any(String), expect.any(String)],
      )

      // 4) queued deletion (Hapus baris on an already-loaded draft row).
      expect(run).toHaveBeenNthCalledWith(
        4,
        expect.stringContaining('DELETE FROM grading_detail WHERE id = ?'),
        ['detail-to-remove'],
      )

      // Re-reads via getDraftWithDetails() to return the freshly saved
      // state.
      expect(query).toHaveBeenCalledTimes(2)
      expect(query).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining('FROM grading_record WHERE id = ?'),
        ['draft-2'],
      )
      expect(result).toEqual({ record: savedRecord, details: savedDetails })
    })

    it('does not touch checked_by/acknowledged_by at all — saveDraft() never writes either column', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query).mockResolvedValueOnce([makeDraftRecord({ status: 'saved' })]).mockResolvedValueOnce([])

      await saveDraft('draft-2b', makeValidHeaderData(), makeValidDetailRows(), [])

      const [headerSql, headerParams] = vi.mocked(run).mock.calls[0]
      expect(headerSql).not.toContain('checked_by')
      expect(headerSql).not.toContain('acknowledged_by')
      // 12 positional params for the header UPDATE — none of them are a
      // checked_by/acknowledged_by value.
      expect(headerParams).toHaveLength(12)
    })

    it('falls back to null for optional header string fields left blank', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query).mockResolvedValueOnce([makeDraftRecord({ status: 'saved' })]).mockResolvedValueOnce([])

      const headerData = makeValidHeaderData({ license_plate_no: '', division: '', note: '' })
      await saveDraft('draft-3', headerData, makeValidDetailRows(), [])

      const [, params] = vi.mocked(run).mock.calls[0]
      expect(params?.[3]).toBeNull() // license_plate_no
      expect(params?.[6]).toBeNull() // division
      expect(params?.[9]).toBeNull() // note
    })
  })

  // screen-011--form-grading business_logic — 'Pause' (checkpoint save, no
  // required-field validation).
  describe('pauseDraftWithFormData()', () => {
    it('saves as-is with NO validation, even with missing required header fields and an empty detailRows array', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query).mockResolvedValueOnce([makeDraftRecord({ status: 'draft_paused' })]).mockResolvedValueOnce([])

      const incompleteHeader = makeValidHeaderData({
        grading_number: '',
        weighbridge_record_id: '',
        vehicle_code: '',
        estate_supplier: '',
        netto: null,
        quantity: null,
      })

      await expect(pauseDraftWithFormData('draft-4', incompleteHeader, [], [])).resolves.toBeDefined()

      expect(run).toHaveBeenCalledTimes(1)
      expect(run).toHaveBeenCalledWith(
        expect.stringContaining("status = 'draft_paused'"),
        expect.arrayContaining(['draft-4']),
      )
    })

    it('applies the same upsert/delete semantics for detail rows as saveDraft(), with no gate before it runs', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      const pausedRecord = makeDraftRecord({ id: 'draft-5', status: 'draft_paused' })
      const pausedDetails = [makeDetailRow({ id: 'detail-existing-2', grading_record_id: 'draft-5' })]
      vi.mocked(query).mockResolvedValueOnce([pausedRecord]).mockResolvedValueOnce(pausedDetails)

      const detailRows: GradingDetailFormRow[] = [
        { id: 'detail-existing-2', grading_parameter_id: 'param-1', quantity: 5, uom: 'kg', percentage: 40 },
        { grading_parameter_id: 'param-2', quantity: 2, uom: 'bunch', percentage: 20 },
      ]

      const result = await pauseDraftWithFormData('draft-5', makeValidHeaderData(), detailRows, ['detail-remove-2'])

      expect(run).toHaveBeenCalledTimes(4)
      expect(run).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining("status = 'draft_paused'"),
        expect.any(Array),
      )
      expect(run).toHaveBeenNthCalledWith(
        2,
        expect.stringContaining('UPDATE grading_detail'),
        expect.arrayContaining(['param-1', 5, 'kg', 40]),
      )
      expect(run).toHaveBeenNthCalledWith(
        3,
        expect.stringContaining('INSERT INTO grading_detail'),
        expect.arrayContaining(['draft-5', 'param-2', 2, 'bunch', 20]),
      )
      expect(run).toHaveBeenNthCalledWith(
        4,
        expect.stringContaining('DELETE FROM grading_detail WHERE id = ?'),
        ['detail-remove-2'],
      )
      expect(result).toEqual({ record: pausedRecord, details: pausedDetails })
    })
  })

  // screen-008--monitor-grading (list-view rewrite) business_logic step 2 —
  // every local grading_record the current user has ongoing or paused,
  // most-recently-updated first (mirrors
  // weighbridgeRecordRepo.spec.ts's getDrafts()).
  describe('getDrafts()', () => {
    it('returns the draft/pause list mapped from the query rows, most-recently-updated first', async () => {
      const rows = [
        { id: 'draft-newest', status: 'draft_paused' as const, grading_number: 'GR-200', updated_at: '2026-08-18T10:00:00.000Z' },
        { id: 'draft-oldest', status: 'draft_ongoing' as const, grading_number: 'GR-100', updated_at: '2026-08-18T08:00:00.000Z' },
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

  // screen-014--data-preview-grading (list-view) business_logic step 1 —
  // every local grading_record row for the current user, regardless of
  // status, most-recently-updated first (mirrors
  // weighbridgeRecordRepo.ts's own getAllRecords() — note
  // weighbridgeRecordRepo.spec.ts itself has no equivalent test block for
  // it yet, so this one is written directly against gradingRecordRepo.ts's
  // own SQL/behavior rather than copied from an existing reference test).
  describe('getAllRecords()', () => {
    it('returns every record for the user regardless of status (draft_ongoing/draft_paused/saved/synced)', async () => {
      const rows = [
        makeDraftRecord({ id: 'rec-ongoing', status: 'draft_ongoing', grading_number: 'GR-001' }),
        makeDraftRecord({ id: 'rec-paused', status: 'draft_paused', grading_number: 'GR-002' }),
        makeDraftRecord({ id: 'rec-saved', status: 'saved', grading_number: 'GR-003' }),
        makeDraftRecord({ id: 'rec-synced', status: 'synced', grading_number: 'GR-004' }),
      ]
      vi.mocked(query).mockResolvedValueOnce(rows)

      const result = await getAllRecords(USER_ID)

      expect(result).toEqual(rows)
      expect(query).toHaveBeenCalledTimes(1)
      expect(query).toHaveBeenCalledWith(
        expect.stringContaining('SELECT * FROM grading_record WHERE created_by = ?'),
        [USER_ID],
      )
    })

    it('orders results by updated_at DESC (most-recently-updated first)', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      await getAllRecords(USER_ID)

      expect(query).toHaveBeenCalledWith(expect.stringContaining('ORDER BY updated_at DESC'), [USER_ID])
    })

    it('returns an empty array when the user has no grading_record rows at all', async () => {
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
  })

  // screen-008--monitor-grading "today's counter" addition — count + sums
  // across every local grading_record for the current user dated today
  // (mirrors weighbridgeRecordRepo.spec.ts's getTodaySummary()).
  describe('getTodaySummary()', () => {
    it("computes today's counter (count, sum netto, sum quantity) across records dated today, regardless of status", async () => {
      vi.mocked(query).mockResolvedValueOnce([{ count_grading: 3, sum_netto: 24000, sum_quantity: 6 }])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countGrading: 3, sumNetto: 24000, sumQuantity: 6 })
      expect(query).toHaveBeenCalledTimes(1)
      expect(query).toHaveBeenCalledWith(
        expect.stringContaining("date(date) = date('now', 'localtime')"),
        [USER_ID],
      )
      expect(query).toHaveBeenCalledWith(expect.not.stringContaining('AND status'), [USER_ID])
    })

    it('excludes records whose date is not today from the counter (aggregate row already reflects only the date-filtered rows)', async () => {
      vi.mocked(query).mockResolvedValueOnce([{ count_grading: 1, sum_netto: 10000, sum_quantity: 1 }])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countGrading: 1, sumNetto: 10000, sumQuantity: 1 })
    })

    it("returns zero counter values when no records match today's date (empty aggregate row / empty table)", async () => {
      vi.mocked(query).mockResolvedValueOnce([{ count_grading: 0, sum_netto: 0, sum_quantity: 0 }])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countGrading: 0, sumNetto: 0, sumQuantity: 0 })
    })

    it('falls back to zero counter values when the aggregate query returns no row at all', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countGrading: 0, sumNetto: 0, sumQuantity: 0 })
    })
  })
})
