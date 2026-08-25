/**
 * depricarpingRecordRepo.spec.ts — screen-039--monitor-depricarping /
 * screen-043--form-depricarping / screen-047--data-preview-depricarping.
 *
 * Unit tests for src/services/depricarpingRecordRepo.ts — covers the
 * authoritative unit_test_cases for these 3 screens' repo layer (mobile-only
 * / local-SQLite-only, no REST api_contract). Mirrors
 * pressingRecordRepo.spec.ts's structure/pattern, adapted for Depricarping's
 * DYNAMIC add-row/remove-row detail grid (REVISED 2026-08-24,
 * entity-catalog v12 — replaces the original FIXED 24-row design):
 * createDraft() inserts ONLY the header, saveDraft()/
 * pauseDraftWithFormData() upsert/delete depricarping_detail rows via
 * applyDetailRowChanges(), mirroring pressingRecordRepo.ts's
 * applyDetailRowChanges() exactly — plus Depricarping's own header field
 * (`presser_id`) and 8 reading columns (7 numeric + 1 free-text `findings`,
 * plus the numeric `downtime_minutes` — a structural difference from
 * Threshing/Pressing's single free-text "Downtime Reason" column, where a
 * row counts as filled when EITHER downtime_minutes OR findings is set).
 *
 * '@/services/localDb' is mocked at module level — both `query()` and
 * `run()` are mocked. No real SQLite connection is touched.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/localDb', () => ({
  query: vi.fn(),
  run: vi.fn(),
}))

import { query, run } from '@/services/localDb'
import {
  canonicalTimeSlots,
  createDraft,
  deleteDraft,
  getAllRecords,
  getDraftWithDetails,
  getDrafts,
  getTodaySummary,
  pauseDraftWithFormData,
  saveDraft,
  DepricarpingDetailRequiredError,
  type DepricarpingDetailFormRow,
  type DepricarpingDetailRow,
  type DepricarpingHeaderFormData,
  type DepricarpingRecord,
} from '@/services/depricarpingRecordRepo'

const USER_ID = 'user-1'

function makeRecord(overrides: Partial<DepricarpingRecord> = {}): DepricarpingRecord {
  return {
    id: 'draft-1',
    station_id: 'station-1',
    presser_id: null,
    date: null,
    note: null,
    checked_by: null,
    acknowledged_by: null,
    status: 'draft_ongoing',
    created_by: USER_ID,
    created_at: '2026-08-24T07:00:00.000Z',
    updated_at: '2026-08-24T07:00:00.000Z',
    ...overrides,
  }
}

function makeDetailRow(overrides: Partial<DepricarpingDetailRow> = {}): DepricarpingDetailRow {
  return {
    id: 'detail-1',
    depricarping_record_id: 'draft-1',
    time_slot: '07:00',
    fan_static_pressure_mmh2o: null,
    polishing_drum_speed_rpm: null,
    air_velocity_ms: null,
    fibre_moisture_percent: null,
    kernel_recovery_in_fibre_percent: null,
    nut_silo_1_temp_c: null,
    nut_silo_2_temp_c: null,
    downtime_minutes: null,
    findings: null,
    created_at: '2026-08-24T07:00:00.000Z',
    updated_at: '2026-08-24T07:00:00.000Z',
    ...overrides,
  }
}

function makeHeaderData(overrides: Partial<DepricarpingHeaderFormData> = {}): DepricarpingHeaderFormData {
  return {
    presser_id: 'DP-01',
    date: '2026-08-24T07:00:00',
    note: '',
    checked_by: '',
    acknowledged_by: '',
    ...overrides,
  }
}

function makeDetailFormRow(overrides: Partial<DepricarpingDetailFormRow> = {}): DepricarpingDetailFormRow {
  return {
    time_slot: '07:00',
    fan_static_pressure_mmh2o: null,
    polishing_drum_speed_rpm: null,
    air_velocity_ms: null,
    fibre_moisture_percent: null,
    kernel_recovery_in_fibre_percent: null,
    nut_silo_1_temp_c: null,
    nut_silo_2_temp_c: null,
    downtime_minutes: null,
    findings: null,
    ...overrides,
  }
}

function makeValidDetailRows(overrides: Partial<DepricarpingDetailFormRow>[] = []): DepricarpingDetailFormRow[] {
  if (overrides.length === 0) {
    return [makeDetailFormRow({ time_slot: '07:00', fan_static_pressure_mmh2o: 45 })]
  }

  return overrides.map((o) => makeDetailFormRow(o))
}

describe('depricarpingRecordRepo', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('canonicalTimeSlots()', () => {
    it('returns 24 hourly slots starting at 07:00 and wrapping through 06:00', () => {
      const slots = canonicalTimeSlots()

      expect(slots).toHaveLength(24)
      expect(slots[0]).toBe('07:00')
      expect(slots[16]).toBe('23:00')
      expect(slots[17]).toBe('00:00')
      expect(slots[23]).toBe('06:00')
    })
  })

  describe('createDraft()', () => {
    it('inserts a new draft_ongoing depricarping_record with date auto-filled, and inserts NO depricarping_detail rows', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })

      const id = await createDraft(USER_ID)

      expect(typeof id).toBe('string')
      expect(run).toHaveBeenCalledTimes(1) // header INSERT only — no pre-created detail rows

      const [headerSql, headerParams] = vi.mocked(run).mock.calls[0]
      expect(headerSql).toContain('INSERT INTO depricarping_record')
      expect(headerSql).toContain("VALUES (?, 'draft_ongoing', ?, ?, ?, ?)")
      expect(headerParams).toEqual([id, USER_ID, expect.any(String), expect.any(String), expect.any(String)])
    })
  })

  describe('getDrafts()', () => {
    it('returns the draft/pause list, most-recently-updated first', async () => {
      const rows = [
        { id: 'draft-newest', status: 'draft_paused' as const, presser_id: 'DP-002', updated_at: '2026-08-24T10:00:00.000Z' },
        { id: 'draft-oldest', status: 'draft_ongoing' as const, presser_id: 'DP-001', updated_at: '2026-08-24T08:00:00.000Z' },
      ]
      vi.mocked(query).mockResolvedValueOnce(rows)

      const result = await getDrafts(USER_ID)

      expect(result).toEqual(rows)
      expect(query).toHaveBeenCalledWith(expect.stringContaining("status IN ('draft_ongoing', 'draft_paused')"), [
        USER_ID,
      ])
      expect(query).toHaveBeenCalledWith(expect.stringContaining('ORDER BY updated_at DESC'), [USER_ID])
    })

    it('returns an empty array when no draft/pause records exist', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getDrafts(USER_ID)

      expect(result).toEqual([])
    })
  })

  describe('getTodaySummary()', () => {
    it("computes today's record count and a plain COUNT of depricarping_detail rows belonging to those records (not filtered by filled-ness)", async () => {
      vi.mocked(query)
        .mockResolvedValueOnce([{ id: 'rec-1' }, { id: 'rec-2' }])
        .mockResolvedValueOnce([{ detail_row_count: 5 }])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countDepricarpingRecord: 2, detailRowCount: 5 })
      expect(query).toHaveBeenNthCalledWith(1, expect.stringContaining('FROM depricarping_record'), [USER_ID])
      expect(query).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining("date(date) = date('now', 'localtime')"),
        [USER_ID],
      )
      const [countSql, countParams] = vi.mocked(query).mock.calls[1]
      expect(countSql).toContain('SELECT COUNT(*) AS detail_row_count')
      expect(countSql).toContain('FROM depricarping_detail')
      expect(countSql).toContain('WHERE depricarping_record_id IN (?, ?)')
      expect(countParams).toEqual(['rec-1', 'rec-2'])
    })

    it('returns zero counters and skips the detail query when no records match today', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getTodaySummary(USER_ID)

      expect(result).toEqual({ countDepricarpingRecord: 0, detailRowCount: 0 })
      expect(query).toHaveBeenCalledTimes(1)
    })

    it('defaults detailRowCount to 0 when the count query returns no row at all', async () => {
      vi.mocked(query).mockResolvedValueOnce([{ id: 'rec-1' }]).mockResolvedValueOnce([])

      const result = await getTodaySummary(USER_ID)

      expect(result.detailRowCount).toBe(0)
    })
  })

  describe('getAllRecords()', () => {
    it('returns every record for the user regardless of status', async () => {
      const rows = [makeRecord({ id: 'rec-a', status: 'saved' }), makeRecord({ id: 'rec-b', status: 'synced' })]
      vi.mocked(query).mockResolvedValueOnce(rows)

      const result = await getAllRecords(USER_ID)

      expect(result).toEqual(rows)
      expect(query).toHaveBeenCalledWith(expect.stringContaining('FROM depricarping_record WHERE created_by = ?'), [
        USER_ID,
      ])
      expect(query).toHaveBeenCalledWith(expect.stringContaining('ORDER BY updated_at DESC'), [USER_ID])
    })
  })

  describe('getDraftWithDetails()', () => {
    it('returns the header row plus its detail rows sorted into canonical time-slot order, however many rows exist', async () => {
      const record = makeRecord({ id: 'draft-1' })
      const unordered = [
        makeDetailRow({ id: 'd-2', time_slot: '09:00' }),
        makeDetailRow({ id: 'd-1', time_slot: '07:00' }),
        makeDetailRow({ id: 'd-3', time_slot: '00:00' }),
      ]
      vi.mocked(query).mockResolvedValueOnce([record]).mockResolvedValueOnce(unordered)

      const result = await getDraftWithDetails('draft-1')

      expect(result?.record).toEqual(record)
      expect(result?.details.map((d) => d.time_slot)).toEqual(['07:00', '09:00', '00:00'])
    })

    it('returns an empty details array when the record has no detail rows yet (brand-new draft)', async () => {
      const record = makeRecord({ id: 'draft-new' })
      vi.mocked(query).mockResolvedValueOnce([record]).mockResolvedValueOnce([])

      const result = await getDraftWithDetails('draft-new')

      expect(result?.details).toEqual([])
    })

    it('returns null when no header row matches', async () => {
      vi.mocked(query).mockResolvedValueOnce([])

      const result = await getDraftWithDetails('missing')

      expect(result).toBeNull()
      expect(query).toHaveBeenCalledTimes(1)
    })
  })

  describe('saveDraft()', () => {
    it('throws DepricarpingDetailRequiredError and calls neither run() nor query() when details is empty', async () => {
      const headerData = makeHeaderData()

      await expect(saveDraft('draft-3', headerData, [], [], 'operator')).rejects.toBeInstanceOf(
        DepricarpingDetailRequiredError,
      )
      expect(run).not.toHaveBeenCalled()
      expect(query).not.toHaveBeenCalled()
    })

    it('throws DepricarpingDetailRequiredError when every row lacks a selected time_slot or has zero reading columns filled', async () => {
      const headerData = makeHeaderData()
      const details: DepricarpingDetailFormRow[] = [
        makeDetailFormRow({ time_slot: null }),
        makeDetailFormRow({ time_slot: '08:00' }), // no readings filled
      ]

      await expect(saveDraft('draft-3z', headerData, details, [], 'operator')).rejects.toBeInstanceOf(
        DepricarpingDetailRequiredError,
      )
      expect(run).not.toHaveBeenCalled()
    })

    it('treats a row with only findings filled as satisfying the minimum-one-row rule', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeRecord({ status: 'saved' })])
        .mockResolvedValueOnce([makeDetailRow()])

      const headerData = makeHeaderData()
      const details = [makeDetailFormRow({ time_slot: '07:00', findings: 'Fan belt loose' })]

      await expect(saveDraft('draft-1', headerData, details, [], 'operator')).resolves.toBeTruthy()
    })

    it('treats a row with only downtime_minutes filled as satisfying the minimum-one-row rule', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeRecord({ status: 'saved' })])
        .mockResolvedValueOnce([makeDetailRow()])

      const headerData = makeHeaderData()
      const details = [makeDetailFormRow({ time_slot: '07:00', downtime_minutes: 15 })]

      await expect(saveDraft('draft-1', headerData, details, [], 'operator')).resolves.toBeTruthy()
    })

    it('updates the header with status=saved, INSERTs a new (id-less) detail row, and returns the freshly saved draft', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      const saved = makeRecord({ id: 'draft-4', presser_id: 'DP-01', status: 'saved' })
      const savedDetails = [makeDetailRow({ id: 'detail-new-1', depricarping_record_id: 'draft-4' })]
      vi.mocked(query).mockResolvedValueOnce([saved]).mockResolvedValueOnce(savedDetails)

      const headerData = makeHeaderData()
      const details = makeValidDetailRows([{ time_slot: '09:00', fan_static_pressure_mmh2o: 45 }])

      const result = await saveDraft('draft-4', headerData, details, [], 'operator')

      // 1 header UPDATE + 1 detail-row INSERT = 2 run() calls.
      expect(run).toHaveBeenCalledTimes(2)

      const [headerSql, headerParams] = vi.mocked(run).mock.calls[0]
      expect(headerSql).toContain('UPDATE depricarping_record')
      expect(headerSql).toContain("status = 'saved'")
      expect(headerParams).toEqual(
        expect.arrayContaining(['DP-01', expect.any(String), null, null, expect.any(String), 'draft-4']),
      )

      const [detailSql, detailParams] = vi.mocked(run).mock.calls[1]
      expect(detailSql).toContain('INSERT INTO depricarping_detail')
      expect(detailParams).toEqual([
        expect.any(String), // freshly generated detail id
        'draft-4',
        '09:00',
        45,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        expect.any(String),
        expect.any(String),
      ])

      expect(query).toHaveBeenCalledTimes(2)
      expect(result).toEqual({ record: saved, details: savedDetails })
    })

    it('UPDATEs an existing detail row (has an id) instead of inserting it', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeRecord({ id: 'draft-5', status: 'saved' })])
        .mockResolvedValueOnce([makeDetailRow({ id: 'detail-existing-1' })])

      const headerData = makeHeaderData()
      const details: DepricarpingDetailFormRow[] = [
        {
          id: 'detail-existing-1',
          time_slot: '11:00',
          fan_static_pressure_mmh2o: 47,
          polishing_drum_speed_rpm: null,
          air_velocity_ms: null,
          fibre_moisture_percent: null,
          kernel_recovery_in_fibre_percent: null,
          nut_silo_1_temp_c: null,
          nut_silo_2_temp_c: null,
          downtime_minutes: null,
          findings: null,
        },
      ]

      await saveDraft('draft-5', headerData, details, [], 'operator')

      expect(run).toHaveBeenCalledTimes(2)
      const [detailSql, detailParams] = vi.mocked(run).mock.calls[1]
      expect(detailSql).toContain('UPDATE depricarping_detail')
      expect(detailParams).toEqual([
        '11:00',
        47,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        expect.any(String),
        'detail-existing-1',
      ])
    })

    it('upserts multiple detail rows sequentially, in details order (mixed insert + update)', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeRecord({ id: 'draft-6', status: 'saved' })])
        .mockResolvedValueOnce([])

      const headerData = makeHeaderData()
      const details: DepricarpingDetailFormRow[] = [
        {
          id: 'detail-existing-2',
          time_slot: '07:00',
          fan_static_pressure_mmh2o: 1,
          polishing_drum_speed_rpm: null,
          air_velocity_ms: null,
          fibre_moisture_percent: null,
          kernel_recovery_in_fibre_percent: null,
          nut_silo_1_temp_c: null,
          nut_silo_2_temp_c: null,
          downtime_minutes: null,
          findings: null,
        },
        makeDetailFormRow({ time_slot: '13:00', fan_static_pressure_mmh2o: 2 }),
      ]

      await saveDraft('draft-6', headerData, details, [], 'operator')

      // header UPDATE + 2 detail-row upserts = 3 run() calls, header first,
      // rows in array order.
      expect(run).toHaveBeenCalledTimes(3)
      const [firstRowSql] = vi.mocked(run).mock.calls[1]
      const [secondRowSql] = vi.mocked(run).mock.calls[2]
      expect(firstRowSql).toContain('UPDATE depricarping_detail')
      expect(secondRowSql).toContain('INSERT INTO depricarping_detail')
    })

    // pending-deletion — queued row ids are DELETEd after the upserts.
    it('DELETEs every id in detailIdsToDelete, after the upserts', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeRecord({ id: 'draft-6b', status: 'saved' })])
        .mockResolvedValueOnce([])

      const headerData = makeHeaderData()
      const details = makeValidDetailRows()

      await saveDraft('draft-6b', headerData, details, ['detail-removed-1', 'detail-removed-2'], 'operator')

      // header UPDATE + 1 upsert + 2 deletes = 4 run() calls.
      expect(run).toHaveBeenCalledTimes(4)
      const [deleteSql1, deleteParams1] = vi.mocked(run).mock.calls[2]
      const [deleteSql2, deleteParams2] = vi.mocked(run).mock.calls[3]
      expect(deleteSql1).toContain('DELETE FROM depricarping_detail WHERE id = ?')
      expect(deleteParams1).toEqual(['detail-removed-1'])
      expect(deleteSql2).toContain('DELETE FROM depricarping_detail WHERE id = ?')
      expect(deleteParams2).toEqual(['detail-removed-2'])
    })

    it('nulls/strips checked_by for a non-supervisor role', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeRecord({ status: 'saved' })])
        .mockResolvedValueOnce([makeDetailRow()])

      const headerData = makeHeaderData({ checked_by: 'Snuck In' })
      const details = makeValidDetailRows()

      await saveDraft('draft-1', headerData, details, [], 'operator')

      const [, headerParams] = vi.mocked(run).mock.calls[0]
      // checked_by is the 4th positional SET param (index 3): presser_id,
      // date, note, checked_by, acknowledged_by, updated_at, [WHERE id].
      expect(headerParams[3]).toBeNull()
    })

    it('keeps checked_by for a supervisor role', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeRecord({ status: 'saved' })])
        .mockResolvedValueOnce([makeDetailRow()])

      const headerData = makeHeaderData({ checked_by: 'usr-supervisor-1' })
      const details = makeValidDetailRows()

      await saveDraft('draft-1', headerData, details, [], 'supervisor')

      const [, headerParams] = vi.mocked(run).mock.calls[0]
      expect(headerParams[3]).toBe('usr-supervisor-1')
    })

    it('nulls/strips acknowledged_by for a non-mill_management role', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeRecord({ status: 'saved' })])
        .mockResolvedValueOnce([makeDetailRow()])

      const headerData = makeHeaderData({ acknowledged_by: 'Snuck In' })
      const details = makeValidDetailRows()

      await saveDraft('draft-1', headerData, details, [], 'supervisor')

      const [, headerParams] = vi.mocked(run).mock.calls[0]
      expect(headerParams[4]).toBeNull()
    })

    it('keeps acknowledged_by for a mill_management role', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeRecord({ status: 'saved' })])
        .mockResolvedValueOnce([makeDetailRow()])

      const headerData = makeHeaderData({ acknowledged_by: 'usr-mill-mgmt-1' })
      const details = makeValidDetailRows()

      await saveDraft('draft-1', headerData, details, [], 'mill_management')

      const [, headerParams] = vi.mocked(run).mock.calls[0]
      expect(headerParams[4]).toBe('usr-mill-mgmt-1')
    })

    it('throws when getDraftWithDetails() re-read after save unexpectedly finds nothing', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query).mockResolvedValueOnce([]) // header re-read finds nothing

      const headerData = makeHeaderData()
      const details = makeValidDetailRows()

      await expect(saveDraft('draft-8', headerData, details, [], 'operator')).rejects.toThrow(
        'Gagal memuat ulang data depricarping setelah disimpan.',
      )
    })
  })

  describe('pauseDraftWithFormData()', () => {
    it('saves with status=draft_paused even when details is empty (no validation gate)', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeRecord({ status: 'draft_paused' })])
        .mockResolvedValueOnce([])

      const headerData = makeHeaderData({ presser_id: '' })

      const result = await pauseDraftWithFormData('draft-1', headerData, [], [], 'operator')

      expect(run).toHaveBeenCalledTimes(1) // header UPDATE only, no rows to upsert
      const [headerSql] = vi.mocked(run).mock.calls[0]
      expect(headerSql).toContain("status = 'draft_paused'")
      expect(result.record.status).toBe('draft_paused')
    })

    it('upserts detail rows and applies pending deletions, same contract as saveDraft()', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeRecord({ status: 'draft_paused' })])
        .mockResolvedValueOnce([])

      const headerData = makeHeaderData()
      const details = makeValidDetailRows()

      await pauseDraftWithFormData('draft-1', headerData, details, ['detail-removed-1'], 'operator')

      // header UPDATE + 1 upsert + 1 delete = 3 run() calls.
      expect(run).toHaveBeenCalledTimes(3)
      const [deleteSql, deleteParams] = vi.mocked(run).mock.calls[2]
      expect(deleteSql).toContain('DELETE FROM depricarping_detail WHERE id = ?')
      expect(deleteParams).toEqual(['detail-removed-1'])
    })

    it('strips checked_by/acknowledged_by by role, same as saveDraft()', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query)
        .mockResolvedValueOnce([makeRecord({ status: 'draft_paused' })])
        .mockResolvedValueOnce([])

      const headerData = makeHeaderData({ checked_by: 'Snuck In', acknowledged_by: 'Snuck In Too' })

      await pauseDraftWithFormData('draft-1', headerData, [], [], 'operator')

      const [, headerParams] = vi.mocked(run).mock.calls[0]
      expect(headerParams[3]).toBeNull()
      expect(headerParams[4]).toBeNull()
    })

    it('throws when getDraftWithDetails() re-read after pause unexpectedly finds nothing', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })
      vi.mocked(query).mockResolvedValueOnce([])

      const headerData = makeHeaderData()

      await expect(pauseDraftWithFormData('draft-1', headerData, [], [], 'operator')).rejects.toThrow(
        'Gagal menyimpan progres depricarping setelah pause.',
      )
    })
  })

  describe('deleteDraft()', () => {
    it('deletes depricarping_detail rows before deleting the depricarping_record row', async () => {
      vi.mocked(run).mockResolvedValue({ changes: 1 })

      await deleteDraft('draft-1')

      expect(run).toHaveBeenCalledTimes(2)
      expect(run).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining('DELETE FROM depricarping_detail WHERE depricarping_record_id = ?'),
        ['draft-1'],
      )
      expect(run).toHaveBeenNthCalledWith(
        2,
        expect.stringContaining('DELETE FROM depricarping_record WHERE id = ?'),
        ['draft-1'],
      )
    })
  })
})
