/**
 * solidWasteDisposalRecordRepo.spec.ts — screen-061--monitor-solid-waste-disposal
 * / screen-071--form-solid-waste-disposal / screen-081--data-preview-solid-waste-disposal.
 *
 * Unit tests for src/services/solidWasteDisposalRecordRepo.ts. Mirrors
 * cagesTrackRecordRepo.spec.ts's structure/pattern — '@/services/localDb' is
 * mocked at module level (both query()/run()), no real SQLite connection is
 * touched.
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
  getLastEventSummary,
  getTodaySummary,
  pauseDraftWithFormData,
  saveDraft,
  SolidWasteDisposalDetailRequiredError,
  type SolidWasteDisposalDetailFormRow,
  type SolidWasteDisposalDetailRow,
  type SolidWasteDisposalDraftListItem,
  type SolidWasteDisposalHeaderFormData,
  type SolidWasteDisposalRecord,
} from '@/services/solidWasteDisposalRecordRepo'

const USER_ID = 'user-1'

function makeRecord(overrides: Partial<SolidWasteDisposalRecord> = {}): SolidWasteDisposalRecord {
  return {
    id: 'draft-1',
    station_id: 'station-1',
    solid_waste_disposal_id: null,
    date: null,
    note: null,
    checked_by: null,
    acknowledged_by: null,
    status: 'draft_ongoing',
    created_by: USER_ID,
    created_at: '2026-08-31T07:00:00.000Z',
    updated_at: '2026-08-31T07:00:00.000Z',
    ...overrides,
  }
}

function makeDetailRow(overrides: Partial<SolidWasteDisposalDetailRow> = {}): SolidWasteDisposalDetailRow {
  return {
    id: 'detail-1',
    solid_waste_disposal_record_id: 'draft-1',
    event_date: '2026-08-31',
    shift: 'Shift 1',
    weighbridge_ticket_no: null,
    vehicle_no: 'B 1234 XY',
    driver_name: null,
    solid_waste_type: 'Empty Bunch',
    source_station: null,
    gross_weight_mt: 10,
    tare_weight_mt: 2,
    net_weight_mt: 8,
    disposal_utilization_site: null,
    purpose_end_use: null,
    gate_pass_no: null,
    security_seal_no: null,
    operator_id: null,
    remarks: null,
    findings: null,
    created_at: '2026-08-31T07:00:00.000Z',
    updated_at: '2026-08-31T07:00:00.000Z',
    ...overrides,
  }
}

function makeHeaderFormData(overrides: Partial<SolidWasteDisposalHeaderFormData> = {}): SolidWasteDisposalHeaderFormData {
  return {
    solid_waste_disposal_id: 'SWD-3001',
    date: '2026-08-31',
    note: '',
    checked_by: '',
    acknowledged_by: '',
    ...overrides,
  }
}

function makeDetailFormRow(overrides: Partial<SolidWasteDisposalDetailFormRow> = {}): SolidWasteDisposalDetailFormRow {
  return {
    event_date: '2026-08-31',
    shift: null,
    weighbridge_ticket_no: null,
    vehicle_no: null,
    driver_name: null,
    solid_waste_type: null,
    source_station: null,
    gross_weight_mt: null,
    tare_weight_mt: null,
    net_weight_mt: null,
    disposal_utilization_site: null,
    purpose_end_use: null,
    gate_pass_no: null,
    security_seal_no: null,
    operator_id: null,
    remarks: null,
    findings: null,
    ...overrides,
  }
}

function makeDraftListItem(overrides: Partial<SolidWasteDisposalDraftListItem> & { id: string }): SolidWasteDisposalDraftListItem {
  return {
    status: 'draft_ongoing',
    solid_waste_disposal_id: 'SWD-001',
    updated_at: '2026-08-31T08:00:00.000Z',
    ...overrides,
  }
}

describe('solidWasteDisposalRecordRepo', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('createDraft()', () => {
    it('INSERTs a new draft record with status=draft_ongoing and date auto-filled to today', async () => {
      vi.mocked(run).mockResolvedValueOnce(undefined as never)

      const id = await createDraft(USER_ID)

      expect(id).toBeTruthy()
      expect(run).toHaveBeenCalledTimes(1)
      expect(run).toHaveBeenCalledWith(
        expect.stringContaining("INSERT INTO solid_waste_disposal_record"),
        expect.arrayContaining([expect.any(String), USER_ID, expect.any(String), expect.any(String), expect.any(String)]),
      )
    })
  })

  describe('getTodaySummary()', () => {
    it('returns zero counters when no records are dated today', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countRecords: 0, countEvents: 0 })
      expect(query).toHaveBeenCalledTimes(1)
    })

    it('counts today\'s records and sums their detail row count via a scoped second query', async () => {
      vi.mocked(query)
        .mockResolvedValueOnce([{ id: 'rec-1' }, { id: 'rec-2' }])
        .mockResolvedValueOnce([{ count: 5 }])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countRecords: 2, countEvents: 5 })
      expect(query).toHaveBeenCalledTimes(2)
      expect(query).toHaveBeenNthCalledWith(2, expect.stringContaining('solid_waste_disposal_detail'), ['rec-1', 'rec-2'])
    })
  })

  describe('getLastEventSummary()', () => {
    it('returns null when the user has no logged events', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getLastEventSummary(USER_ID)

      expect(result).toBeNull()
    })

    it('returns the most recently logged event', async () => {
      vi.mocked(query).mockResolvedValueOnce([
        { solid_waste_disposal_record_id: 'rec-1', event_date: '2026-08-31', solid_waste_type: 'Fibre', vehicle_no: 'B 999 ZZ' },
      ])

      const result = await getLastEventSummary(USER_ID)

      expect(result).toEqual({
        recordId: 'rec-1',
        eventDate: '2026-08-31',
        solidWasteType: 'Fibre',
        vehicleNo: 'B 999 ZZ',
      })
    })
  })

  describe('getDrafts()', () => {
    it('returns draft/pause records for the current user, most-recently-updated first', async () => {
      vi.mocked(query).mockResolvedValueOnce([
        { id: 'd1', status: 'draft_ongoing', solid_waste_disposal_id: 'SWD-A', updated_at: '2026-08-31T09:00:00.000Z' },
      ])

      const result = await getDrafts(USER_ID)

      expect(result).toEqual([makeDraftListItem({ id: 'd1', updated_at: '2026-08-31T09:00:00.000Z', solid_waste_disposal_id: 'SWD-A' })])
      expect(query).toHaveBeenCalledWith(expect.stringContaining("status IN ('draft_ongoing', 'draft_paused')"), [USER_ID])
    })
  })

  describe('getAllRecords()', () => {
    it('returns every record for the user regardless of status', async () => {
      const records = [makeRecord({ status: 'saved' })]
      vi.mocked(query).mockResolvedValueOnce(records)

      const result = await getAllRecords(USER_ID)

      expect(result).toEqual(records)
      expect(query).toHaveBeenCalledWith(expect.stringContaining('WHERE created_by = ?'), [USER_ID])
    })
  })

  describe('getDraftWithDetails()', () => {
    it('returns null when no header row matches', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getDraftWithDetails('missing-id')

      expect(result).toBeNull()
      expect(query).toHaveBeenCalledTimes(1)
    })

    it('returns the header row plus its ordered detail rows', async () => {
      const record = makeRecord()
      const detail = makeDetailRow()
      vi.mocked(query).mockResolvedValueOnce([record]).mockResolvedValueOnce([detail])

      const result = await getDraftWithDetails('draft-1')

      expect(result).toEqual({ record, details: [detail] })
    })
  })

  describe('saveDraft()', () => {
    it('throws SolidWasteDisposalDetailRequiredError and does not write when zero rows have an event_date', async () => {
      await expect(
        saveDraft('draft-1', makeHeaderFormData(), [makeDetailFormRow({ event_date: null })], [], 'operator'),
      ).rejects.toBeInstanceOf(SolidWasteDisposalDetailRequiredError)

      expect(run).not.toHaveBeenCalled()
    })

    it('strips checked_by to null for a non-supervisor role even when provided', async () => {
      vi.mocked(run).mockResolvedValue(undefined as never)
      vi.mocked(query).mockResolvedValueOnce([makeRecord({ status: 'saved' })]).mockResolvedValueOnce([])

      await saveDraft('draft-1', makeHeaderFormData({ checked_by: 'someone' }), [makeDetailFormRow()], [], 'operator')

      expect(run).toHaveBeenCalledWith(
        expect.stringContaining('UPDATE solid_waste_disposal_record'),
        expect.arrayContaining([null]),
      )
    })

    it('keeps checked_by for a supervisor role', async () => {
      vi.mocked(run).mockResolvedValue(undefined as never)
      vi.mocked(query).mockResolvedValueOnce([makeRecord({ status: 'saved' })]).mockResolvedValueOnce([])

      await saveDraft('draft-1', makeHeaderFormData({ checked_by: 'sup-1' }), [makeDetailFormRow()], [], 'supervisor')

      expect(run).toHaveBeenCalledWith(
        expect.stringContaining('UPDATE solid_waste_disposal_record'),
        expect.arrayContaining(['sup-1']),
      )
    })

    it('updates the header with status=saved and upserts detail rows', async () => {
      vi.mocked(run).mockResolvedValue(undefined as never)
      const savedRecord = makeRecord({ status: 'saved' })
      const savedDetail = makeDetailRow()
      vi.mocked(query).mockResolvedValueOnce([savedRecord]).mockResolvedValueOnce([savedDetail])

      const result = await saveDraft('draft-1', makeHeaderFormData(), [makeDetailFormRow({ id: 'existing-detail' })], ['old-id'], 'operator')

      expect(result).toEqual({ record: savedRecord, details: [savedDetail] })
      expect(run).toHaveBeenCalledWith(expect.stringContaining("status = 'saved'"), expect.any(Array))
      expect(run).toHaveBeenCalledWith(expect.stringContaining('UPDATE solid_waste_disposal_detail'), expect.any(Array))
      expect(run).toHaveBeenCalledWith(expect.stringContaining('DELETE FROM solid_waste_disposal_detail WHERE id = ?'), ['old-id'])
    })
  })

  describe('pauseDraftWithFormData()', () => {
    it('updates the header with status=draft_paused without requiring any valid detail row', async () => {
      vi.mocked(run).mockResolvedValue(undefined as never)
      const pausedRecord = makeRecord({ status: 'draft_paused' })
      vi.mocked(query).mockResolvedValueOnce([pausedRecord]).mockResolvedValueOnce([])

      const result = await pauseDraftWithFormData('draft-1', makeHeaderFormData(), [], [], 'operator')

      expect(result.record.status).toBe('draft_paused')
      expect(run).toHaveBeenCalledWith(expect.stringContaining("status = 'draft_paused'"), expect.any(Array))
    })
  })

  describe('deleteDraft()', () => {
    it('deletes detail rows then the header row', async () => {
      vi.mocked(run).mockResolvedValue(undefined as never)

      await deleteDraft('draft-1')

      expect(run).toHaveBeenNthCalledWith(1, expect.stringContaining('DELETE FROM solid_waste_disposal_detail'), ['draft-1'])
      expect(run).toHaveBeenNthCalledWith(2, expect.stringContaining('DELETE FROM solid_waste_disposal_record'), ['draft-1'])
    })
  })
})
