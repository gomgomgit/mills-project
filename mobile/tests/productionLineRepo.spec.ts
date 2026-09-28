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

/**
 * fetchProductionLinesForReport() — endpoint kedua, ditambahkan 2026-09-28
 * untuk kelima layar laporan mobile. Berbeda dari fetchCurrentProductionLines()
 * yang swa-cakup, endpoint ini menerima business_unit_id sehingga Admin —
 * yang tidak terikat mill — dapat memilih line mill yang ia pilih.
 *
 * Yang dijaga di sini: parameter itu hanya berangkat bila ADA nilainya.
 * Mengirim `business_unit_id=` kosong bukan hal yang netral — server akan
 * memperlakukannya sebagai "tidak memilih mill", dan bagi Admin itu berarti
 * daftar lintas mill, bukan daftar kosong.
 */
describe('productionLineRepo — fetchProductionLinesForReport()', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('GETs /api/production-lines/options-for-report with business_unit_id when one is given', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: { data: [{ id: 'pl-1', name: 'Line 01', code: 'L1' }] },
    })

    const result = await productionLineRepo.fetchProductionLinesForReport('bu-9')

    expect(apiClient.get).toHaveBeenCalledWith('/api/production-lines/options-for-report', {
      params: { business_unit_id: 'bu-9' },
    })
    // Bentuk barisnya sama persis dengan fetchCurrentProductionLines() —
    // tidak ada pemeta kedua.
    expect(result).toEqual([{ id: 'pl-1', name: 'Line 01', code: 'L1' }])
  })

  it('omits business_unit_id entirely when it is null/undefined — never sends an empty one', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: { data: [] } })

    await productionLineRepo.fetchProductionLinesForReport(null)
    await productionLineRepo.fetchProductionLinesForReport()

    expect(apiClient.get).toHaveBeenNthCalledWith(1, '/api/production-lines/options-for-report', { params: {} })
    expect(apiClient.get).toHaveBeenNthCalledWith(2, '/api/production-lines/options-for-report', { params: {} })
  })

  it('returns an empty array when the response has no data', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: {} })

    await expect(productionLineRepo.fetchProductionLinesForReport('bu-1')).resolves.toEqual([])
  })

  it('propagates a rejection (offline/422/401) to the caller', async () => {
    vi.mocked(apiClient.get).mockRejectedValue(new Error('offline'))

    await expect(productionLineRepo.fetchProductionLinesForReport('bu-1')).rejects.toThrow('offline')
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
    // 1 DELETE (clears legacy synthetic rows for this business unit) + 2 upserts.
    expect(run).toHaveBeenCalledTimes(3)
    expect(run).toHaveBeenNthCalledWith(
      1,
      expect.stringContaining('DELETE FROM station WHERE business_unit_id = ? AND production_line_id IS NULL'),
      ['bu-1'],
    )
    expect(run).toHaveBeenCalledWith(
      expect.stringContaining('INSERT INTO station'),
      expect.arrayContaining(['station-real-1', 'bu-1', 'pl-1', 'Weighbridge', 'weighbridge', 1, null]),
    )
    expect(run).toHaveBeenCalledWith(
      expect.stringContaining('ON CONFLICT(id) DO UPDATE'),
      expect.arrayContaining(['station-real-2', 'bu-1', 'pl-1', 'Cages Track', 'cages-track', 1, 'truck']),
    )
  })

  it('still clears legacy synthetic rows even when the response has no stations to upsert', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: {} })

    await productionLineRepo.fetchAndCacheStationsForProductionLine('pl-1', 'bu-1')

    expect(run).toHaveBeenCalledTimes(1)
    expect(run).toHaveBeenCalledWith(
      expect.stringContaining('DELETE FROM station WHERE business_unit_id = ? AND production_line_id IS NULL'),
      ['bu-1'],
    )
  })

  it('propagates a rejection (offline) to the caller', async () => {
    vi.mocked(apiClient.get).mockRejectedValue(new Error('offline'))

    await expect(
      productionLineRepo.fetchAndCacheStationsForProductionLine('pl-1', 'bu-1'),
    ).rejects.toThrow('offline')
    expect(run).not.toHaveBeenCalled()
  })
})
