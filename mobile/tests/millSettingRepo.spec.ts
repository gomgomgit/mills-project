/**
 * millSettingRepo.spec.ts — Mills Setting feature (screen-034, plus
 * consumers screen-005--home / screen-006--station-list /
 * screen-012--form-cages-track).
 *
 * Unit tests for src/services/millSettingRepo.ts's getMillSetting() /
 * getJumlahCages() — covers scoping by business unit, snake_case ->
 * camelCase normalization, and the "not synced yet" (null) case.
 *
 * '@/services/localDb' is mocked at module level, same convention as
 * stationRepo.spec.ts — no real SQLite connection is touched.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/localDb', () => ({
  query: vi.fn(),
}))

import { query } from '@/services/localDb'
import { getJumlahCages, getMillSetting, type MillSetting } from '@/services/millSettingRepo'

const BUSINESS_UNIT_ID = 'bu-1'

describe('millSettingRepo — getMillSetting()', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('queries the local mill_setting table filtered by the given business unit', async () => {
    vi.mocked(query).mockResolvedValue([])

    await getMillSetting(BUSINESS_UNIT_ID)

    expect(query).toHaveBeenCalledTimes(1)
    expect(query).toHaveBeenCalledWith(expect.stringContaining('FROM mill_setting WHERE business_unit_id = ?'), [
      BUSINESS_UNIT_ID,
    ])
  })

  it('normalizes a snake_case row into a camelCase MillSetting object', async () => {
    vi.mocked(query).mockResolvedValue([
      {
        id: 'mill-setting-1',
        business_unit_id: BUSINESS_UNIT_ID,
        app_name: 'Mill A',
        logo: 'storage/logo.png',
        home_page_image: 'storage/home.png',
        jumlah_cages: 10,
      },
    ])

    const result = await getMillSetting(BUSINESS_UNIT_ID)

    const expected: MillSetting = {
      id: 'mill-setting-1',
      businessUnitId: BUSINESS_UNIT_ID,
      appName: 'Mill A',
      logo: 'storage/logo.png',
      homePageImage: 'storage/home.png',
      jumlahCages: 10,
    }
    expect(result).toEqual(expected)
  })

  it('returns null when no mill-setting has been synced yet for the business unit', async () => {
    vi.mocked(query).mockResolvedValue([])

    const result = await getMillSetting(BUSINESS_UNIT_ID)

    expect(result).toBeNull()
  })
})

describe('millSettingRepo — getJumlahCages()', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns jumlah_cages from the cached mill-setting', async () => {
    vi.mocked(query).mockResolvedValue([
      {
        id: 'mill-setting-1',
        business_unit_id: BUSINESS_UNIT_ID,
        app_name: 'Mill A',
        logo: null,
        home_page_image: null,
        jumlah_cages: 8,
      },
    ])

    const result = await getJumlahCages(BUSINESS_UNIT_ID)

    expect(result).toBe(8)
  })

  it('returns null when no mill-setting is cached yet (distinct from a cached value of 0)', async () => {
    vi.mocked(query).mockResolvedValue([])

    const result = await getJumlahCages(BUSINESS_UNIT_ID)

    expect(result).toBeNull()
  })
})
