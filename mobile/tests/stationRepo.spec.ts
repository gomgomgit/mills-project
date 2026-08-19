/**
 * stationRepo.spec.ts — screen-006--station-list / usecase-006--station-list
 * "Pilih Stasiun" business_logic step 1 (load cached station list from
 * local storage).
 *
 * Unit tests for src/services/stationRepo.ts's
 * getActiveAndPlaceholderStations() — covers scoping by business unit and
 * snake_case -> camelCase / is_active normalization (INTEGER 0/1 from the
 * real native SQLite driver as well as a native boolean, since mocked test
 * rows may use either shape — see stationRepo.ts's toStationSlot()
 * comment).
 *
 * '@/services/localDb' is mocked at module level (per localDb.ts's header
 * comment: "screens 006-015 and this screen's own tests exercise it
 * exclusively through vi.mock('@/services/localDb') overriding query()")
 * — no real SQLite connection is touched.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/localDb', () => ({
  query: vi.fn(),
}))

import { query } from '@/services/localDb'
import { getActiveAndPlaceholderStations, type StationSlot } from '@/services/stationRepo'

const BUSINESS_UNIT_ID = 'bu-1'

describe('stationRepo — getActiveAndPlaceholderStations()', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('queries the local station table filtered by the given business unit', async () => {
    vi.mocked(query).mockResolvedValue([])

    await getActiveAndPlaceholderStations(BUSINESS_UNIT_ID)

    expect(query).toHaveBeenCalledTimes(1)
    expect(query).toHaveBeenCalledWith(expect.stringContaining('FROM station WHERE business_unit_id = ?'), [
      BUSINESS_UNIT_ID,
    ])
  })

  it('normalizes snake_case rows into camelCase StationSlot objects, with is_active as INTEGER (0/1)', async () => {
    vi.mocked(query).mockResolvedValue([
      {
        id: 'station-1',
        business_unit_id: BUSINESS_UNIT_ID,
        name: 'Timbangan',
        type: 'weighbridge',
        is_active: 1,
        icon: 'truck',
      },
      {
        id: 'station-2',
        business_unit_id: BUSINESS_UNIT_ID,
        name: 'Stasiun X',
        type: 'other',
        is_active: 0,
        icon: null,
      },
    ])

    const result = await getActiveAndPlaceholderStations(BUSINESS_UNIT_ID)

    const expected: StationSlot[] = [
      {
        id: 'station-1',
        businessUnitId: BUSINESS_UNIT_ID,
        name: 'Timbangan',
        type: 'weighbridge',
        isActive: true,
        icon: 'truck',
      },
      {
        id: 'station-2',
        businessUnitId: BUSINESS_UNIT_ID,
        name: 'Stasiun X',
        type: 'other',
        isActive: false,
        icon: null,
      },
    ]
    expect(result).toEqual(expected)
  })

  it('normalizes a missing/undefined icon column value to null (entity-catalog v7)', async () => {
    vi.mocked(query).mockResolvedValue([
      {
        id: 'station-4',
        business_unit_id: BUSINESS_UNIT_ID,
        name: 'Cages Track',
        type: 'cages-track',
        is_active: 1,
        icon: undefined,
      },
    ])

    const result = await getActiveAndPlaceholderStations(BUSINESS_UNIT_ID)

    expect(result[0].icon).toBeNull()
  })

  it('also normalizes is_active when the mocked row already provides a native boolean', async () => {
    vi.mocked(query).mockResolvedValue([
      {
        id: 'station-3',
        business_unit_id: BUSINESS_UNIT_ID,
        name: 'Grading',
        type: 'grading',
        is_active: true,
      },
    ])

    const result = await getActiveAndPlaceholderStations(BUSINESS_UNIT_ID)

    expect(result[0].isActive).toBe(true)
  })

  it('returns an empty list when no rows match the business unit', async () => {
    vi.mocked(query).mockResolvedValue([])

    const result = await getActiveAndPlaceholderStations(BUSINESS_UNIT_ID)

    expect(result).toEqual([])
  })
})
