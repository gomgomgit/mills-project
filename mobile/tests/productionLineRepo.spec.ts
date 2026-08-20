/**
 * productionLineRepo.spec.ts — screen-006--station-list /
 * usecase-006--station-list "Pilih Stasiun", Production Line picker step
 * (entity-catalog v9, 2026-08-20).
 *
 * '@/services/localDb' and '@/services/apiClient' are mocked at module
 * level (same convention as syncService.spec.ts) — no real SQLite
 * connection or HTTP call is made.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/localDb', () => ({
  run: vi.fn(),
  query: vi.fn(),
}))

vi.mock('@/services/apiClient', () => ({
  default: { get: vi.fn() },
}))

import { run } from '@/services/localDb'
import apiClient from '@/services/apiClient'
import { productionLineRepo } from '@/services/productionLineRepo'

describe('productionLineRepo — fetchCurrentProductionLines()', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('GETs /api/production-lines/current and returns the data array', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { data: [{ id: 'pl-1', name: 'Line 01', code: null }] },
    })

    const result = await productionLineRepo.fetchCurrentProductionLines()

    expect(apiClient.get).toHaveBeenCalledWith('/api/production-lines/current')
    expect(result).toEqual([{ id: 'pl-1', name: 'Line 01', code: null }])
  })

  it('returns an empty array when the response has no data', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: {} })

    const result = await productionLineRepo.fetchCurrentProductionLines()

    expect(result).toEqual([])
  })

  it('propagates a rejection (offline/404) to the caller', async () => {
    vi.mocked(apiClient.get).mockRejectedValue(new Error('offline'))

    await expect(productionLineRepo.fetchCurrentProductionLines()).rejects.toThrow('offline')
  })
})

describe('productionLineRepo — fetchAndCacheStationsForProductionLine()', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(run).mockResolvedValue({ changes: 1 })
  })

  it('GETs the current-stations endpoint with production_line_id and upserts each row by its real id', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        data: [
          { id: 'station-real-1', name: 'Weighbridge', type: 'weighbridge', icon: null, is_active: true, machinery_count: null },
          { id: 'station-real-2', name: 'Cages Track', type: 'cages-track', icon: 'truck', is_active: true, machinery_count: 4 },
        ],
      },
    })

    await productionLineRepo.fetchAndCacheStationsForProductionLine('pl-1', 'bu-1')

    expect(apiClient.get).toHaveBeenCalledWith('/api/production-lines/current/stations', {
      params: { production_line_id: 'pl-1' },
    })
    expect(run).toHaveBeenCalledTimes(2)
    expect(run).toHaveBeenCalledWith(
      expect.stringContaining('INSERT INTO station'),
      expect.arrayContaining(['station-real-1', 'bu-1', 'pl-1', 'Weighbridge', 'weighbridge', 1, null]),
    )
    expect(run).toHaveBeenCalledWith(
      expect.stringContaining('ON CONFLICT(id) DO UPDATE'),
      expect.arrayContaining(['station-real-2', 'bu-1', 'pl-1', 'Cages Track', 'cages-track', 1, 'truck']),
    )
  })

  it('does nothing when the response has no stations', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: {} })

    await productionLineRepo.fetchAndCacheStationsForProductionLine('pl-1', 'bu-1')

    expect(run).not.toHaveBeenCalled()
  })

  it('propagates a rejection (offline) to the caller', async () => {
    vi.mocked(apiClient.get).mockRejectedValue(new Error('offline'))

    await expect(
      productionLineRepo.fetchAndCacheStationsForProductionLine('pl-1', 'bu-1'),
    ).rejects.toThrow('offline')
    expect(run).not.toHaveBeenCalled()
  })
})
