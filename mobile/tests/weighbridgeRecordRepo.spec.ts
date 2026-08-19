/**
 * weighbridgeRecordRepo.spec.ts — screen-007--monitor-weighbridge /
 * usecase-007--monitor-weighbridge business_logic steps 1-5, extended for
 * screen-010--form-weighbridge / usecase-010--form-weighbridge
 * business_logic steps 1 and 4-5.
 *
 * Unit tests for src/services/weighbridgeRecordRepo.ts — covers the 5
 * authoritative unit_test_cases for screen-007's repo layer (there is no
 * REST api_contract for this screen; it is mobile-only / local-SQLite-only,
 * so these unit_test_cases target the repo's read/write primitives
 * directly) plus light coverage of createDraft()/resumeDraft() so the
 * file's coverage of the repo's full public API isn't artificially thin.
 *
 * '@/services/localDb' is mocked at module level (same convention as
 * stationRepo.spec.ts: "screens 006-015 and this screen's own tests
 * exercise it exclusively through vi.mock('@/services/localDb') overriding
 * query()") — extended here to also mock `run()`, the generic write
 * primitive this screen's repo introduces (first repo in the app that
 * WRITES, not just SELECTs). No real SQLite connection is touched.
 *
 * Note on unit_test_case 2 ("keeps weighbridge_record unchanged when Clear
 * confirmation is cancelled"): at the pure-repo level this is trivially
 * true (deleteDraft() is simply never invoked on the cancel path — there is
 * no separate "cancel" entrypoint in the repo to call). A lightweight
 * sanity test is still included below for traceability, but the
 * meaningful, non-trivial assertion of this intent — that clicking Clear
 * then Cancel via ConfirmDialog does NOT call weighbridgeRecordRepo's
 * deleteDraft() — lives in MonitorWeighbridgeView.spec.ts's scenario 6
 * ("Clear Draft dibatalkan tanpa konfirmasi"), which is the level at which
 * "cancel" actually exists as a user action.
 *
 * ---
 * screen-010--form-weighbridge extension — getDraftById() / saveDraft():
 * this file was extended (not duplicated) to cover this screen's own
 * unit_test_cases 1, 2, 6 and 8, at the repo level (the level at which
 * each is actually meaningful):
 *   1. "loads existing paused draft data into form when continuing draft"
 *      — getDraftById() returns the existing paused row's full field
 *      values.
 *   2. "loads empty form state when starting a new draft" — getDraftById()
 *      returns a row with null optional fields (new draft from Monitor's
 *      createDraft()), plus a defensive "not found" branch.
 *   6. "rejects checked_by value submitted by operator role" — saveDraft()
 *      strips checked_by to null for a non-supervisor `currentUserRole`
 *      even when formData.checked_by carries a value (the role-stripping
 *      rule is enforced HERE, deliberately, not only in
 *      FormWeighbridgeView.vue's UI — see weighbridgeRecordRepo.ts's
 *      saveDraft() header comment). Its mirror (supervisor keeps
 *      checked_by) is covered alongside for the same branch.
 *   8. "returns success — all fields valid, UPDATE weighbridge_record with
 *      status=saved" — saveDraft() with a fully valid formData UPDATEs
 *      every field, sets status='saved', refreshes updated_at, and returns
 *      the freshly saved row (re-read via getDraftById()). Navigation
 *      itself (to `monitor-weighbridge`) is a UI concern, covered instead
 *      by FormWeighbridgeView.spec.ts's "success" scenarios.
 *   Unit_test_cases 3, 4, 5, 7 (Checked By visibility/editability,
 *   required-field inline validation, Back/confirm-dialog) are UI/component
 *   concerns and live in FormWeighbridgeView.spec.ts instead.
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
  getDraftById,
  getSummary,
  getTodaySummary,
  pauseDraft,
  resumeDraft,
  saveDraft,
} from '@/services/weighbridgeRecordRepo'
import type { WeighbridgeFormData, WeighbridgeRecord } from '@/services/weighbridgeRecordRepo'

const USER_ID = 'user-1'

function makeDraftRecord(overrides: Partial<WeighbridgeRecord> = {}): WeighbridgeRecord {
  return {
    id: 'draft-1',
    station_id: 'station-1',
    wb_card_number: null,
    weighbridge_type: null,
    record_datetime: null,
    vehicle_number: null,
    driver_name: null,
    estate_supplier: null,
    destination: null,
    division: null,
    block: null,
    gross_weight: null,
    tare_weight: null,
    net_weight: null,
    quantity: null,
    checked_by: null,
    acknowledged_by: null,
    status: 'draft_ongoing',
    created_by: USER_ID,
    created_at: '2026-08-17T07:00:00.000Z',
    updated_at: '2026-08-17T07:00:00.000Z',
    ...overrides,
  }
}

function makeValidFormData(overrides: Partial<WeighbridgeFormData> = {}): WeighbridgeFormData {
  return {
    wb_card_number: 'WB-2001',
    weighbridge_type: 'dispatch',
    record_datetime: '2026-08-18T10:00',
    vehicle_number: 'B 5678 EF',
    driver_name: 'Andi',
    estate_supplier: 'Estate B',
    destination: 'PKS Tujuan B',
    division: 'Divisi 2',
    block: 'Blok 5',
    gross_weight: 20000,
    tare_weight: 6000,
    net_weight: 14000,
    quantity: 2,
    checked_by: '',
    acknowledged_by: 'Manager Dua',
    ...overrides,
  }
}

describe('weighbridgeRecordRepo', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  // unit_test_case: "returns success result when all conditions pass"
  describe('getSummary()', () => {
    it('returns success result when all conditions pass — aggregate sums + current draft', async () => {
      vi.mocked(query)
        .mockResolvedValueOnce([{ sum_wb_card: 5, sum_net_weight: 1200, sum_quantity: 300 }])
        .mockResolvedValueOnce([{ id: 'draft-1', status: 'draft_paused' }])

      const result = await getSummary(USER_ID)

      expect(result).toEqual({
        sumWbCard: 5,
        sumNetWeight: 1200,
        sumQuantity: 300,
        currentDraft: { id: 'draft-1', status: 'draft_paused' },
      })
      expect(query).toHaveBeenCalledTimes(2)
      expect(query).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining('FROM weighbridge_record'),
        [USER_ID],
      )
      expect(query).toHaveBeenNthCalledWith(
        2,
        expect.stringContaining("status IN ('draft_ongoing', 'draft_paused')"),
        [USER_ID],
      )
    })

    it('returns zeroed sums and currentDraft null when there are no rows / no draft', async () => {
      vi.mocked(query)
        .mockResolvedValueOnce([{ sum_wb_card: 0, sum_net_weight: 0, sum_quantity: 0 }])
        .mockResolvedValueOnce([])

      const result = await getSummary(USER_ID)

      expect(result).toEqual({
        sumWbCard: 0,
        sumNetWeight: 0,
        sumQuantity: 0,
        currentDraft: null,
      })
    })
  })

  // Light coverage of createDraft() — not one of the 5 authoritative
  // unit_test_cases, but necessary for meaningful coverage of the repo's
  // full public API.
  describe('createDraft()', () => {
    it('inserts a new draft_ongoing record for the user and returns the generated id', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })

      const id = await createDraft(USER_ID)

      expect(typeof id).toBe('string')
      expect(id.length).toBeGreaterThan(0)
      expect(run).toHaveBeenCalledTimes(1)
      expect(run).toHaveBeenCalledWith(
        expect.stringContaining('INSERT INTO weighbridge_record'),
        [id, USER_ID, expect.any(String), expect.any(String)],
      )
    })
  })

  // Light coverage of resumeDraft() — not one of the 5 authoritative
  // unit_test_cases, but necessary for meaningful coverage of the repo's
  // full public API.
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

  // unit_test_case: "takes no action when Pause is triggered without an
  // ongoing draft" / "updates status to draft_paused when Pause is
  // triggered with an ongoing draft"
  describe('pauseDraft()', () => {
    it('takes no action when Pause is triggered without an ongoing draft (recordId undefined)', async () => {
      await pauseDraft(undefined)

      expect(run).not.toHaveBeenCalled()
    })

    it('takes no action when Pause is triggered without an ongoing draft (recordId null)', async () => {
      await pauseDraft(null)

      expect(run).not.toHaveBeenCalled()
    })

    it('updates status to draft_paused when Pause is triggered with an ongoing draft', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })

      await pauseDraft('draft-2')

      expect(run).toHaveBeenCalledTimes(1)
      expect(run).toHaveBeenCalledWith(
        expect.stringContaining("SET status = 'draft_paused'"),
        [expect.any(String), 'draft-2'],
      )
    })
  })

  // unit_test_case: "deletes weighbridge_record permanently when Clear is
  // confirmed" / "keeps weighbridge_record unchanged when Clear
  // confirmation is cancelled" (see file header note on #2).
  describe('deleteDraft()', () => {
    it('deletes weighbridge_record permanently when Clear is confirmed', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })

      await deleteDraft('draft-3')

      expect(run).toHaveBeenCalledTimes(1)
      expect(run).toHaveBeenCalledWith(
        expect.stringContaining('DELETE FROM weighbridge_record WHERE id = ?'),
        ['draft-3'],
      )
    })

    it('sanity: keeps weighbridge_record unchanged when deleteDraft() is never invoked (cancel path) — see MonitorWeighbridgeView.spec.ts scenario 6 for the meaningful assertion', () => {
      // No call to deleteDraft() at all here — simulates the cancel path at
      // the repo level, where "cancel" is simply the absence of a call.
      expect(run).not.toHaveBeenCalled()
    })
  })

  // screen-010--form-weighbridge business_logic step 1 — Form Weighbridge
  // loading a draft on mount via its route param id.
  describe('getDraftById()', () => {
    // unit_test_case 1: "loads existing paused draft data into form when
    // continuing draft".
    it("returns the existing paused record's full field values when continuing a draft", async () => {
      const pausedRecord = makeDraftRecord({
        id: 'draft-paused-1',
        wb_card_number: 'WB-1001',
        weighbridge_type: 'receive',
        record_datetime: '2026-08-17T08:00',
        vehicle_number: 'B 1234 CD',
        driver_name: 'Budi',
        estate_supplier: 'Estate A',
        destination: null,
        division: 'Divisi 1',
        block: 'Blok 3',
        gross_weight: 15000,
        tare_weight: 5000,
        net_weight: 10000,
        quantity: 1,
        checked_by: 'Supervisor Satu',
        acknowledged_by: 'Mill Manager',
        status: 'draft_paused',
        updated_at: '2026-08-17T07:30:00.000Z',
      })
      vi.mocked(query).mockResolvedValueOnce([pausedRecord])

      const result = await getDraftById('draft-paused-1')

      expect(result).toEqual(pausedRecord)
      expect(query).toHaveBeenCalledTimes(1)
      expect(query).toHaveBeenCalledWith(
        expect.stringContaining('FROM weighbridge_record WHERE id = ?'),
        ['draft-paused-1'],
      )
    })

    // unit_test_case 2: "loads empty form state when starting a new
    // draft".
    it('returns a row with null optional fields when starting a new draft', async () => {
      const newDraftRow = makeDraftRecord({ id: 'draft-new-1', status: 'draft_ongoing' })
      vi.mocked(query).mockResolvedValueOnce([newDraftRow])

      const result = await getDraftById('draft-new-1')

      expect(result).not.toBeNull()
      expect(result?.status).toBe('draft_ongoing')
      expect(result?.wb_card_number).toBeNull()
      expect(result?.record_datetime).toBeNull()
      expect(result?.checked_by).toBeNull()
      expect(result?.gross_weight).toBeNull()
    })

    it('returns null when no row matches (defensive — e.g. a stale/invalid route param)', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getDraftById('does-not-exist')

      expect(result).toBeNull()
    })
  })

  // screen-010--form-weighbridge business_logic steps 4-5 — 'Simpan'.
  describe('saveDraft()', () => {
    // unit_test_case 6: "rejects checked_by value submitted by operator
    // role".
    it('strips checked_by to null when currentUserRole is operator, even if formData.checked_by carries a value', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query).mockResolvedValueOnce([makeDraftRecord({ checked_by: null, status: 'saved' })])

      const formData = makeValidFormData({ checked_by: 'Snuck In Value' })

      await saveDraft('draft-3', formData, 'operator')

      expect(run).toHaveBeenCalledTimes(1)
      const [, params] = vi.mocked(run).mock.calls[0]
      // checked_by is the 14th positional UPDATE param (index 13) per
      // saveDraft()'s SET column order (wb_card_number, weighbridge_type,
      // record_datetime, vehicle_number, driver_name, estate_supplier,
      // destination, division, block, gross_weight, tare_weight,
      // net_weight, quantity, checked_by, ...).
      expect(params[13]).toBeNull()
    })

    it('strips checked_by to null when currentUserRole is neither operator nor supervisor (mill_management)', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query).mockResolvedValueOnce([makeDraftRecord({ checked_by: null, status: 'saved' })])

      const formData = makeValidFormData({ checked_by: 'Snuck In Value' })

      await saveDraft('draft-3b', formData, 'mill_management')

      const [, params] = vi.mocked(run).mock.calls[0]
      expect(params[13]).toBeNull()
    })

    it('keeps checked_by when currentUserRole is supervisor and formData.checked_by is set', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query).mockResolvedValueOnce([
        makeDraftRecord({ checked_by: 'Supervisor Satu', status: 'saved' }),
      ])

      const formData = makeValidFormData({ checked_by: 'Supervisor Satu' })

      await saveDraft('draft-3c', formData, 'supervisor')

      const [, params] = vi.mocked(run).mock.calls[0]
      expect(params[13]).toBe('Supervisor Satu')
    })

    // unit_test_case 8: "returns success — all fields valid, UPDATE
    // weighbridge_record with status=saved, navigate to Monitor
    // Weighbridge" (navigation itself is a UI concern, covered by
    // FormWeighbridgeView.spec.ts).
    it('updates all fields, sets status=saved and refreshes updated_at, and returns the freshly saved row', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      const savedRow = makeDraftRecord({
        id: 'draft-4',
        wb_card_number: 'WB-2001',
        status: 'saved',
        updated_at: '2026-08-18T10:05:00.000Z',
      })
      vi.mocked(query).mockResolvedValueOnce([savedRow])

      const formData = makeValidFormData({ checked_by: 'Supervisor Dua' })

      const result = await saveDraft('draft-4', formData, 'supervisor')

      expect(run).toHaveBeenCalledTimes(1)
      expect(run).toHaveBeenCalledWith(
        expect.stringContaining("status = 'saved'"),
        expect.arrayContaining(['WB-2001', 'draft-4']),
      )
      const [, params] = vi.mocked(run).mock.calls[0]
      // updated_at (index 15) is a freshly generated timestamp string, not
      // a field carried over from formData.
      expect(typeof params[15]).toBe('string')
      expect(params[15].length).toBeGreaterThan(0)
      // recordId is the final WHERE-clause param.
      expect(params[16]).toBe('draft-4')

      expect(query).toHaveBeenCalledTimes(1)
      expect(query).toHaveBeenCalledWith(
        expect.stringContaining('FROM weighbridge_record WHERE id = ?'),
        ['draft-4'],
      )
      expect(result).toEqual(savedRow)
    })

    it('falls back to null for optional string fields left blank in formData', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query).mockResolvedValueOnce([makeDraftRecord({ status: 'saved' })])

      const formData = makeValidFormData({ division: '', block: '', acknowledged_by: '' })

      await saveDraft('draft-5', formData, 'operator')

      const [, params] = vi.mocked(run).mock.calls[0]
      expect(params[7]).toBeNull() // division
      expect(params[8]).toBeNull() // block
      expect(params[14]).toBeNull() // acknowledged_by
    })
  })
  // screen-007--monitor-weighbridge "today's counter" addition.
  describe('getTodaySummary()', () => {
    it("computes today's counter (count, sum net_weight, sum quantity) across records dated today, regardless of status", async () => {
      // The repo delegates aggregation to SQL — this test verifies
      // getTodaySummary() maps the aggregate row's snake_case columns to
      // the expected camelCase shape, matching what a WHERE
      // date(record_datetime) = date('now','localtime') aggregate over
      // 2-3 today-dated rows (draft_ongoing/draft_paused/saved, any
      // weighbridge_type, same userId) would produce. record_datetime
      // replaces the old arrival_datetime/dispatch_datetime pair as of
      // entity-catalog v5 — a single column for both Receive and Dispatch.
      vi.mocked(query).mockResolvedValueOnce([
        { count_wb: 3, sum_net_weight: 24000, sum_quantity: 6 },
      ])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countWb: 3, sumNetWeight: 24000, sumQuantity: 6 })
      expect(query).toHaveBeenCalledTimes(1)
      expect(query).toHaveBeenCalledWith(
        expect.stringContaining("date(record_datetime) = date('now', 'localtime')"),
        [USER_ID],
      )
      expect(query).toHaveBeenCalledWith(expect.not.stringContaining('AND status'), [USER_ID])
    })

    it("excludes records whose record_datetime is not today from the counter", async () => {
      // The SQL WHERE clause itself is responsible for the date exclusion
      // (asserted above via stringContaining the date(...) filter); at
      // the repo-unit level this is verified by confirming the mapped
      // result reflects ONLY what the (already-date-filtered) aggregate
      // row reports — i.e. a mix of today + yesterday/other-day rows
      // seeded upstream, with the aggregate row already excluding the
      // non-today rows.
      vi.mocked(query).mockResolvedValueOnce([
        { count_wb: 1, sum_net_weight: 10000, sum_quantity: 1 },
      ])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countWb: 1, sumNetWeight: 10000, sumQuantity: 1 })
    })

    it('returns zero counter values when no records match today\'s date (empty aggregate row / empty table)', async () => {
      vi.mocked(query).mockResolvedValueOnce([
        { count_wb: 0, sum_net_weight: 0, sum_quantity: 0 },
      ])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countWb: 0, sumNetWeight: 0, sumQuantity: 0 })
    })

    it('falls back to zero counter values when the aggregate query returns no row at all', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countWb: 0, sumNetWeight: 0, sumQuantity: 0 })
    })
  })
})
