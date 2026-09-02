/**
 * sterilizerRecordRepo.spec.ts — screen-121--monitor-sterilizer
 * / screen-122--form-sterilizer / screen-123--data-preview-sterilizer.
 *
 * Unit tests for src/services/sterilizerRecordRepo.ts. Mirrors
 * cpoDispatchRecordRepo.spec.ts's structure/pattern — '@/services/localDb'
 * is mocked at module level (both query()/run()), no real SQLite
 * connection is touched. This is the FINAL station of this project.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/localDb', () => ({
  query: vi.fn(),
  run: vi.fn(),
}))

import { query, run } from '@/services/localDb'
import {
  computeDurationMinutes,
  createDraft,
  deleteDraft,
  getAllRecords,
  getDraftWithDetails,
  getDrafts,
  getLastCycleSummary,
  getTodaySummary,
  pauseDraftWithFormData,
  saveDraft,
  SterilizerDetailRequiredError,
  type SterilizerDetailFormRow,
  type SterilizerDetailRow,
  type SterilizerDraftListItem,
  type SterilizerHeaderFormData,
  type SterilizerRecord,
} from '@/services/sterilizerRecordRepo'

const USER_ID = 'user-1'

function makeRecord(overrides: Partial<SterilizerRecord> = {}): SterilizerRecord {
  return {
    id: 'draft-1',
    station_id: 'station-1',
    sterilizer_id: null,
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

function makeDetailRow(overrides: Partial<SterilizerDetailRow> = {}): SterilizerDetailRow {
  return {
    id: 'detail-1',
    sterilizer_record_id: 'draft-1',
    sterilizer_no: '1',
    close_door_time: '07:00',
    peak_1_time: null,
    exhaust_1_time: null,
    peak_2_time: null,
    exhaust_2_time: null,
    peak_3_time: null,
    exhaust_3_time: null,
    open_door_time: '08:10',
    duration_minutes: 70,
    number_of_cages: 10,
    cages_status: null,
    checked_by_spv: false,
    remarks: null,
    created_at: '2026-08-31T07:00:00.000Z',
    updated_at: '2026-08-31T07:00:00.000Z',
    ...overrides,
  }
}

function makeHeaderFormData(overrides: Partial<SterilizerHeaderFormData> = {}): SterilizerHeaderFormData {
  return {
    sterilizer_id: 'STR-3001',
    date: '2026-08-31',
    note: '',
    checked_by: '',
    acknowledged_by: '',
    ...overrides,
  }
}

function makeDetailFormRow(overrides: Partial<SterilizerDetailFormRow> = {}): SterilizerDetailFormRow {
  return {
    sterilizer_no: null,
    close_door_time: '07:00',
    peak_1_time: null,
    exhaust_1_time: null,
    peak_2_time: null,
    exhaust_2_time: null,
    peak_3_time: null,
    exhaust_3_time: null,
    open_door_time: null,
    duration_minutes: null,
    number_of_cages: null,
    cages_status: null,
    checked_by_spv: false,
    remarks: null,
    ...overrides,
  }
}

function makeDraftListItem(overrides: Partial<SterilizerDraftListItem> & { id: string }): SterilizerDraftListItem {
  return {
    status: 'draft_ongoing',
    sterilizer_id: 'STR-001',
    updated_at: '2026-08-31T08:00:00.000Z',
    ...overrides,
  }
}

describe('sterilizerRecordRepo', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('computeDurationMinutes()', () => {
    it('computes open_door_time minus close_door_time in minutes', () => {
      expect(computeDurationMinutes('07:00', '08:10')).toBe(70)
    })

    it('returns null when either time is missing', () => {
      expect(computeDurationMinutes(null, '08:10')).toBeNull()
      expect(computeDurationMinutes('07:00', null)).toBeNull()
    })

    it('wraps across midnight when open_door_time is earlier than close_door_time', () => {
      expect(computeDurationMinutes('23:30', '00:15')).toBe(45)
    })
  })

  describe('createDraft()', () => {
    it('INSERTs a new draft record with status=draft_ongoing and date auto-filled to today', async () => {
      vi.mocked(run).mockResolvedValueOnce(undefined as never)

      const id = await createDraft(USER_ID)

      expect(id).toBeTruthy()
      expect(run).toHaveBeenCalledTimes(1)
      expect(run).toHaveBeenCalledWith(
        expect.stringContaining('INSERT INTO sterilizer_record'),
        expect.arrayContaining([expect.any(String), USER_ID, expect.any(String), expect.any(String), expect.any(String)]),
      )
    })
  })

  describe('getTodaySummary()', () => {
    it('returns zero counters when no records are dated today', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countRecords: 0, countCycles: 0 })
      expect(query).toHaveBeenCalledTimes(1)
    })

    it('counts today\'s records and sums their detail row count via a scoped second query', async () => {
      vi.mocked(query)
        .mockResolvedValueOnce([{ id: 'rec-1' }, { id: 'rec-2' }])
        .mockResolvedValueOnce([{ count: 5 }])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countRecords: 2, countCycles: 5 })
      expect(query).toHaveBeenCalledTimes(2)
      expect(query).toHaveBeenNthCalledWith(2, expect.stringContaining('sterilizer_detail'), ['rec-1', 'rec-2'])
    })
  })

  describe('getLastCycleSummary()', () => {
    it('returns null when the user has no logged cycles', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getLastCycleSummary(USER_ID)

      expect(result).toBeNull()
    })

    it('returns the most recently logged cycle', async () => {
      vi.mocked(query).mockResolvedValueOnce([
        { sterilizer_record_id: 'rec-1', sterilizer_no: '2', close_door_time: '07:00', duration_minutes: 70 },
      ])

      const result = await getLastCycleSummary(USER_ID)

      expect(result).toEqual({
        recordId: 'rec-1',
        sterilizerNo: '2',
        closeDoorTime: '07:00',
        durationMinutes: 70,
      })
    })
  })

  describe('getDrafts()', () => {
    it('returns draft/pause records for the current user, most-recently-updated first', async () => {
      vi.mocked(query).mockResolvedValueOnce([
        { id: 'd1', status: 'draft_ongoing', sterilizer_id: 'STR-A', updated_at: '2026-08-31T09:00:00.000Z' },
      ])

      const result = await getDrafts(USER_ID)

      expect(result).toEqual([makeDraftListItem({ id: 'd1', updated_at: '2026-08-31T09:00:00.000Z', sterilizer_id: 'STR-A' })])
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

    it('returns the header row plus its ordered detail rows, normalizing checked_by_spv to a boolean', async () => {
      const record = makeRecord()
      vi.mocked(query)
        .mockResolvedValueOnce([record])
        .mockResolvedValueOnce([{ ...makeDetailRow(), checked_by_spv: 1 }])

      const result = await getDraftWithDetails('draft-1')

      expect(result).toEqual({ record, details: [makeDetailRow({ checked_by_spv: true })] })
    })
  })

  describe('saveDraft()', () => {
    it('throws SterilizerDetailRequiredError and does not write when zero rows have a close_door_time', async () => {
      await expect(
        saveDraft('draft-1', makeHeaderFormData(), [makeDetailFormRow({ close_door_time: null })], [], 'operator'),
      ).rejects.toBeInstanceOf(SterilizerDetailRequiredError)

      expect(run).not.toHaveBeenCalled()
    })

    it('strips checked_by to null for a non-supervisor role even when provided', async () => {
      vi.mocked(run).mockResolvedValue(undefined as never)
      vi.mocked(query).mockResolvedValueOnce([makeRecord({ status: 'saved' })]).mockResolvedValueOnce([])

      await saveDraft('draft-1', makeHeaderFormData({ checked_by: 'someone' }), [makeDetailFormRow()], [], 'operator')

      expect(run).toHaveBeenCalledWith(
        expect.stringContaining('UPDATE sterilizer_record'),
        expect.arrayContaining([null]),
      )
    })

    it('keeps checked_by for a supervisor role', async () => {
      vi.mocked(run).mockResolvedValue(undefined as never)
      vi.mocked(query).mockResolvedValueOnce([makeRecord({ status: 'saved' })]).mockResolvedValueOnce([])

      await saveDraft('draft-1', makeHeaderFormData({ checked_by: 'sup-1' }), [makeDetailFormRow()], [], 'supervisor')

      expect(run).toHaveBeenCalledWith(
        expect.stringContaining('UPDATE sterilizer_record'),
        expect.arrayContaining(['sup-1']),
      )
    })

    it('updates the header with status=saved and upserts detail rows, computing duration_minutes for the INSERT', async () => {
      vi.mocked(run).mockResolvedValue(undefined as never)
      const savedRecord = makeRecord({ status: 'saved' })
      const savedDetail = makeDetailRow()
      vi.mocked(query).mockResolvedValueOnce([savedRecord]).mockResolvedValueOnce([savedDetail])

      const result = await saveDraft(
        'draft-1',
        makeHeaderFormData(),
        [makeDetailFormRow({ id: 'existing-detail', close_door_time: '07:00', open_door_time: '08:10' })],
        ['old-id'],
        'operator',
      )

      expect(result).toEqual({ record: savedRecord, details: [savedDetail] })
      expect(run).toHaveBeenCalledWith(expect.stringContaining("status = 'saved'"), expect.any(Array))
      expect(run).toHaveBeenCalledWith(
        expect.stringContaining('UPDATE sterilizer_detail'),
        expect.arrayContaining([70]),
      )
      expect(run).toHaveBeenCalledWith(expect.stringContaining('DELETE FROM sterilizer_detail WHERE id = ?'), ['old-id'])
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

      expect(run).toHaveBeenNthCalledWith(1, expect.stringContaining('DELETE FROM sterilizer_detail'), ['draft-1'])
      expect(run).toHaveBeenNthCalledWith(2, expect.stringContaining('DELETE FROM sterilizer_record'), ['draft-1'])
    })
  })
})
