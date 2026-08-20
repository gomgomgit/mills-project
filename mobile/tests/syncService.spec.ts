/**
 * syncService.spec.ts — TEMPORARY manual sync feature (2026-08-20), see
 * syncService.ts's own doc comment for the full mechanism/scope.
 *
 * '@/services/localDb' and '@/services/apiClient' are mocked at module
 * level (same convention as weighbridgeRecordRepo.spec.ts /
 * localSchema.spec.ts) — no real SQLite connection or HTTP call is made.
 * `query()`'s mock implementation branches on the SQL string (same
 * pattern used by localSchema.spec.ts's fetchAndCacheMillSetting tests)
 * since syncService.ts issues several distinct SELECTs per record type.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/localDb', () => ({
  run: vi.fn(),
  query: vi.fn(),
}))

vi.mock('@/services/apiClient', () => ({
  default: { post: vi.fn() },
}))

const { useAuthStoreMock } = vi.hoisted(() => ({ useAuthStoreMock: vi.fn() }))

vi.mock('@/stores/auth', () => ({
  useAuthStore: useAuthStoreMock,
}))

import { query, run } from '@/services/localDb'
import apiClient from '@/services/apiClient'
import { syncAllRecords } from '@/services/syncService'

const PRODUCTION_LINE_ID = 'pl-1'
const USER_ID = 'user-1'

function mockAuth() {
  useAuthStoreMock.mockReturnValue({
    currentUser: { id: USER_ID },
  })
}

describe('syncService — syncAllRecords()', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAuth()
    vi.mocked(run).mockResolvedValue({ changes: 1 })
  })

  it('throws when no productionLineId is given', async () => {
    await expect(syncAllRecords(null)).rejects.toThrow('Tidak dapat sinkronisasi')
    expect(apiClient.post).not.toHaveBeenCalled()
  })

  it('throws when the auth store has no user', async () => {
    useAuthStoreMock.mockReturnValue({ currentUser: null })

    await expect(syncAllRecords(PRODUCTION_LINE_ID)).rejects.toThrow('Tidak dapat sinkronisasi')
    expect(apiClient.post).not.toHaveBeenCalled()
  })

  it('does nothing and reports 0/0 when there are no saved records of any type', async () => {
    vi.mocked(query).mockResolvedValue([])

    const summary = await syncAllRecords(PRODUCTION_LINE_ID)

    expect(summary).toEqual({
      weighbridge: [],
      grading: [],
      cagesTrack: [],
      syncedCount: 0,
      failedCount: 0,
    })
    expect(apiClient.post).not.toHaveBeenCalled()
  })

  it('syncs a saved Weighbridge record: POSTs without station_id, then marks it synced with the server id', async () => {
    vi.mocked(query).mockImplementation(async (sql: string) => {
      if (sql.includes('FROM weighbridge_record')) {
        return [
          {
            id: 'local-wb-1',
            wb_card_number: 'WB-001',
            weighbridge_type: 'receive',
            record_datetime: '2026-08-20T08:00:00Z',
            vehicle_number: 'B 1234 CD',
            driver_name: 'Budi',
            estate_supplier: 'Estate A',
            destination: null,
            division: null,
            block: null,
            gross_weight: 5000,
            tare_weight: 2000,
            quantity: 100,
            checked_by: 'local-user-1',
            acknowledged_by: null,
            server_id: null,
          },
        ]
      }
      return []
    })
    vi.mocked(apiClient.post).mockResolvedValue({ data: { id: 'server-wb-1' } })

    const summary = await syncAllRecords(PRODUCTION_LINE_ID)

    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/weighbridge-records',
      expect.objectContaining({
        production_line_id: PRODUCTION_LINE_ID,
        wb_card_number: 'WB-001',
        checked: true,
        acknowledged: false,
      }),
    )
    const [, payload] = vi.mocked(apiClient.post).mock.calls[0]
    expect(payload).not.toHaveProperty('station_id')

    expect(run).toHaveBeenCalledWith(
      `UPDATE weighbridge_record SET status = 'synced', server_id = ? WHERE id = ?`,
      ['server-wb-1', 'local-wb-1'],
    )
    expect(summary.weighbridge).toEqual([{ id: 'local-wb-1', label: 'WB-001', ok: true }])
    expect(summary.syncedCount).toBe(1)
    expect(summary.failedCount).toBe(0)
  })

  it('reports a failed Weighbridge record with the API error message, and does not update local status', async () => {
    vi.mocked(query).mockImplementation(async (sql: string) => {
      if (sql.includes('FROM weighbridge_record')) {
        return [{ id: 'local-wb-1', wb_card_number: 'WB-001', checked_by: null, acknowledged_by: null, server_id: null }]
      }
      return []
    })
    vi.mocked(apiClient.post).mockRejectedValue({ message: 'WB Card Number wajib diisi.' })

    const summary = await syncAllRecords(PRODUCTION_LINE_ID)

    expect(summary.weighbridge).toEqual([
      { id: 'local-wb-1', label: 'WB-001', ok: false, reason: 'WB Card Number wajib diisi.' },
    ])
    expect(summary.failedCount).toBe(1)
    expect(run).not.toHaveBeenCalledWith(expect.stringContaining('UPDATE weighbridge_record'), expect.anything())
  })

  it('resolves a Grading record\'s weighbridge_record_id from its local parent\'s server_id and includes its details', async () => {
    vi.mocked(query).mockImplementation(async (sql: string, params: unknown[] = []) => {
      if (sql.includes('FROM weighbridge_record WHERE status')) {
        return []
      }
      if (sql.includes('FROM grading_record WHERE status')) {
        return [
          {
            id: 'local-gr-1',
            grading_number: 'GR-001',
            date: '2026-08-20',
            weighbridge_record_id: 'local-wb-1',
            license_plate_no: 'B 1234 CD',
            vehicle_code: null,
            estate_supplier: 'Estate A',
            division: null,
            netto: 9000,
            quantity: 120,
            note: null,
            checked_by: null,
            acknowledged_by: null,
            server_id: null,
          },
        ]
      }
      if (sql.includes('SELECT server_id FROM weighbridge_record')) {
        expect(params).toEqual(['local-wb-1'])
        return [{ server_id: 'server-wb-1' }]
      }
      if (sql.includes('FROM grading_detail')) {
        return [{ grading_parameter_id: 'param-1', quantity: 100 }]
      }
      if (sql.includes('FROM cages_track_record WHERE status')) {
        return []
      }
      return []
    })
    vi.mocked(apiClient.post).mockResolvedValue({ data: { id: 'server-gr-1' } })

    const summary = await syncAllRecords(PRODUCTION_LINE_ID)

    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/grading-records',
      expect.objectContaining({
        weighbridge_record_id: 'server-wb-1',
        details: [{ grading_parameter_id: 'param-1', quantity: 100 }],
      }),
    )
    expect(summary.grading).toEqual([{ id: 'local-gr-1', label: 'GR-001', ok: true }])
  })

  it('fails a Grading record without POSTing when its parent Weighbridge record has not been synced', async () => {
    vi.mocked(query).mockImplementation(async (sql: string) => {
      if (sql.includes('FROM weighbridge_record WHERE status')) {
        return []
      }
      if (sql.includes('FROM grading_record WHERE status')) {
        return [{ id: 'local-gr-1', grading_number: 'GR-001', weighbridge_record_id: 'local-wb-1', checked_by: null, acknowledged_by: null, server_id: null }]
      }
      if (sql.includes('SELECT server_id FROM weighbridge_record')) {
        return [{ server_id: null }]
      }
      return []
    })

    const summary = await syncAllRecords(PRODUCTION_LINE_ID)

    expect(summary.grading).toEqual([
      {
        id: 'local-gr-1',
        label: 'GR-001',
        ok: false,
        reason: 'Weighbridge terkait belum tersinkron — sinkronkan Weighbridge-nya dahulu.',
      },
    ])
    expect(apiClient.post).not.toHaveBeenCalledWith('/api/grading-records', expect.anything())
  })

  it('converts a Cages Track record\'s CSV checked_cage_numbers into an array per detail row', async () => {
    vi.mocked(query).mockImplementation(async (sql: string) => {
      if (sql.includes('FROM weighbridge_record WHERE status') || sql.includes('FROM grading_record WHERE status')) {
        return []
      }
      if (sql.includes('FROM cages_track_record WHERE status')) {
        return [
          {
            id: 'local-ct-1',
            cages_track_number: 'CT-001',
            date: '2026-08-20',
            tippler_start_time: '2026-08-20T08:00:00Z',
            tippler_stop_time: '2026-08-20T09:00:00Z',
            cages_out: 10,
            cages_tipped: 10,
            note: null,
            checked_by: null,
            acknowledged_by: null,
            server_id: null,
          },
        ]
      }
      if (sql.includes('FROM cages_tipped_time')) {
        return [{ tipped_hour: 8, checked_cage_numbers: '1,3,5' }]
      }
      return []
    })
    vi.mocked(apiClient.post).mockResolvedValue({ data: { id: 'server-ct-1' } })

    await syncAllRecords(PRODUCTION_LINE_ID)

    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/cages-track-records',
      expect.objectContaining({
        details: [{ tipped_hour: 8, checked_cage_numbers: ['1', '3', '5'] }],
      }),
    )
  })
})
