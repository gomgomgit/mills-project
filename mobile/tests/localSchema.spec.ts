/**
 * localSchema.spec.ts — screen-006--station-list fix (2026-08-18): the
 * local `station` table was never populated on a real device (no sync
 * flow exists anywhere in this project), leaving Station List
 * permanently empty. Covers `seedDefaultStationsIfNeeded()`, the local,
 * idempotent default seed of the 15 MVP stations that replaces the
 * never-built "separate sync flow" every mobile screen's comments used
 * to assume.
 *
 * '@/services/localDb' is mocked at module level (same convention as
 * stationRepo.spec.ts) — no real SQLite connection is touched.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/localDb', () => ({
  run: vi.fn(),
  query: vi.fn(),
}))

vi.mock('@/services/apiClient', () => ({
  default: { get: vi.fn() },
}))

import { query, run } from '@/services/localDb'
import apiClient from '@/services/apiClient'
import { fetchAndCacheMillSetting, initLocalSchema, seedDefaultStationsIfNeeded } from '@/services/localSchema'

const BUSINESS_UNIT_ID = 'bu-1'

describe('localSchema — seedDefaultStationsIfNeeded()', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(run).mockResolvedValue({ changes: 1 })
  })

  it('deletes any existing rows for the business unit first, then inserts all 15 MVP stations (replace, not merge)', async () => {
    await seedDefaultStationsIfNeeded(BUSINESS_UNIT_ID)

    // 1 DELETE (clears stale/duplicate rows for this business unit) + 15 INSERT.
    expect(run).toHaveBeenCalledTimes(16)
    expect(run).toHaveBeenNthCalledWith(1, expect.stringContaining('DELETE FROM station WHERE business_unit_id = ?'), [
      BUSINESS_UNIT_ID,
    ])
  })

  it('inserts each station as a fresh row (no ON CONFLICT/IGNORE needed — the delete-first replace already guarantees no duplicates)', async () => {
    await seedDefaultStationsIfNeeded(BUSINESS_UNIT_ID)

    const insertCalls = vi.mocked(run).mock.calls.slice(1)
    expect(insertCalls).toHaveLength(15)
    for (const call of insertCalls) {
      expect(call[0]).toContain('INSERT INTO station')
    }
  })

  it('seeds exactly the 3 active MVP station types with is_active=1', async () => {
    await seedDefaultStationsIfNeeded(BUSINESS_UNIT_ID)

    const insertCalls = vi.mocked(run).mock.calls.slice(1)
    const activeCalls = insertCalls.filter((call) => call[1]?.[4] === 1)
    const activeTypes = activeCalls.map((call) => call[1]?.[3])

    expect(activeTypes.sort()).toEqual(['cages-track', 'grading', 'weighbridge'])
  })

  it('seeds the 12 placeholder stations with type=other and is_active=0', async () => {
    await seedDefaultStationsIfNeeded(BUSINESS_UNIT_ID)

    const insertCalls = vi.mocked(run).mock.calls.slice(1)
    const placeholderCalls = insertCalls.filter((call) => call[1]?.[4] === 0)

    expect(placeholderCalls).toHaveLength(12)
    for (const call of placeholderCalls) {
      expect(call[1]?.[3]).toBe('other')
    }
  })

  it('scopes every seeded row to the given business_unit_id and derives a deterministic id from it', async () => {
    await seedDefaultStationsIfNeeded(BUSINESS_UNIT_ID)

    const insertCalls = vi.mocked(run).mock.calls.slice(1)
    for (const call of insertCalls) {
      const [id, businessUnitId] = call[1] ?? []
      expect(businessUnitId).toBe(BUSINESS_UNIT_ID)
      expect(id).toContain(BUSINESS_UNIT_ID)
    }
  })
})

/**
 * initLocalSchema() — v5 weighbridge_record column migration. Reproduces
 * the real bug a user hit: `CREATE TABLE IF NOT EXISTS` is a no-op once a
 * table already exists, so a browser whose weighbridge_record table was
 * created under the pre-v5 shape (arrival_datetime/dispatch_datetime, no
 * weighbridge_type/destination) never picks up the new v5 columns, and
 * every v5 read/write — including gradingRecordRepo.ts's WB Card No
 * dropdown, which selects record_datetime — fails with "no such column:
 * record_datetime".
 */
describe('localSchema — initLocalSchema() weighbridge_record v5 migration', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(run).mockResolvedValue({ changes: 1 })
  })

  it('adds every missing v5 column to weighbridge_record when the table pre-dates the v5 schema', async () => {
    vi.mocked(query).mockImplementation(async (sql: string) => {
      if (sql.includes('weighbridge_record')) {
        return [
          { name: 'id' },
          { name: 'station_id' },
          { name: 'wb_card_number' },
          { name: 'arrival_datetime' },
          { name: 'dispatch_datetime' },
          { name: 'status' },
        ] as never
      }

      return [{ name: 'id' }] as never
    })

    await initLocalSchema()

    const alterCalls = vi.mocked(run).mock.calls.map((call) => call[0])
    expect(alterCalls).toContain('ALTER TABLE weighbridge_record ADD COLUMN weighbridge_type TEXT')
    expect(alterCalls).toContain('ALTER TABLE weighbridge_record ADD COLUMN record_datetime TEXT')
    expect(alterCalls).toContain('ALTER TABLE weighbridge_record ADD COLUMN destination TEXT')
  })

  it('does not re-add a column that PRAGMA table_info already reports as present', async () => {
    vi.mocked(query).mockResolvedValue([
      { name: 'id' },
      { name: 'weighbridge_type' },
      { name: 'record_datetime' },
      { name: 'destination' },
      { name: 'server_id' },
    ] as never)

    await initLocalSchema()

    const alterCalls = vi.mocked(run).mock.calls.map((call) => call[0])
    expect(alterCalls.some((sql) => sql.includes('ALTER TABLE weighbridge_record'))).toBe(false)
  })
})

/**
 * initLocalSchema() — v2 grading_record/grading_detail column migration.
 * Reproduces the real bug a user hit: `CREATE TABLE IF NOT EXISTS` is a
 * no-op once a table already exists, so a browser whose grading_record
 * table was created under the pre-v2 shape never picks up the new v2
 * columns (weighbridge_record_id, etc.) and every v2 read/write fails with
 * "no such column". initLocalSchema() must explicitly ADD any column
 * PRAGMA table_info() reports as missing.
 */
describe('localSchema — initLocalSchema() grading_record/grading_detail v2 migration', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(run).mockResolvedValue({ changes: 1 })
  })

  it('adds every missing v2 column to grading_record when the table pre-dates the v2 schema', async () => {
    vi.mocked(query).mockImplementation(async (sql: string) => {
      if (sql.includes('grading_record')) {
        return [{ name: 'id' }, { name: 'station_id' }, { name: 'status' }] as never
      }

      return [{ name: 'id' }, { name: 'grading_record_id' }] as never
    })

    await initLocalSchema()

    const alterCalls = vi.mocked(run).mock.calls.map((call) => call[0])
    for (const column of ['weighbridge_record_id', 'license_plate_no', 'vehicle_code', 'netto', 'quantity', 'note']) {
      expect(alterCalls).toContain(`ALTER TABLE grading_record ADD COLUMN ${column} ${column === 'netto' || column === 'quantity' ? 'REAL' : 'TEXT'}`)
    }
  })

  it('adds every missing v2 column to grading_detail when the table pre-dates the v2 schema', async () => {
    vi.mocked(query).mockImplementation(async (sql: string) => {
      if (sql.includes('grading_detail')) {
        return [{ name: 'id' }, { name: 'grading_record_id' }, { name: 'category' }] as never
      }

      return [{ name: 'id' }] as never
    })

    await initLocalSchema()

    const alterCalls = vi.mocked(run).mock.calls.map((call) => call[0])
    expect(alterCalls).toContain('ALTER TABLE grading_detail ADD COLUMN grading_parameter_id TEXT')
    expect(alterCalls).toContain('ALTER TABLE grading_detail ADD COLUMN uom TEXT')
    expect(alterCalls).toContain('ALTER TABLE grading_detail ADD COLUMN percentage REAL')
  })

  it('does not re-add a column that PRAGMA table_info already reports as present', async () => {
    vi.mocked(query).mockResolvedValue([
      { name: 'id' },
      { name: 'weighbridge_record_id' },
      { name: 'license_plate_no' },
      { name: 'vehicle_code' },
      { name: 'netto' },
      { name: 'quantity' },
      { name: 'note' },
      { name: 'server_id' },
    ] as never)

    await initLocalSchema()

    const alterCalls = vi.mocked(run).mock.calls.map((call) => call[0])
    expect(alterCalls.some((sql) => sql.includes('ALTER TABLE grading_record'))).toBe(false)
  })
})

/**
 * initLocalSchema() — v3 cages_track_record/cages_tipped_time column
 * migration. Same "CREATE TABLE IF NOT EXISTS is a no-op on an existing
 * table" gap as the grading v2 migration above.
 */
describe('localSchema — initLocalSchema() cages_track_record/cages_tipped_time v3 migration', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(run).mockResolvedValue({ changes: 1 })
  })

  it('adds every missing v3 column to cages_track_record when the table pre-dates the v3 schema', async () => {
    vi.mocked(query).mockImplementation(async (sql: string) => {
      if (sql.includes('cages_track_record')) {
        return [{ name: 'id' }, { name: 'station_id' }, { name: 'status' }] as never
      }

      return [{ name: 'id' }, { name: 'cages_track_record_id' }] as never
    })

    await initLocalSchema()

    const alterCalls = vi.mocked(run).mock.calls.map((call) => call[0])
    expect(alterCalls).toContain('ALTER TABLE cages_track_record ADD COLUMN tippler_start_time TEXT')
    expect(alterCalls).toContain('ALTER TABLE cages_track_record ADD COLUMN tippler_stop_time TEXT')
    expect(alterCalls).toContain('ALTER TABLE cages_track_record ADD COLUMN cages_out INTEGER')
    expect(alterCalls).toContain('ALTER TABLE cages_track_record ADD COLUMN cages_tipped INTEGER')
    expect(alterCalls).toContain('ALTER TABLE cages_track_record ADD COLUMN note TEXT')
  })

  it('adds every missing v3 column to cages_tipped_time when the table pre-dates the v3 schema', async () => {
    vi.mocked(query).mockImplementation(async (sql: string) => {
      if (sql.includes('cages_tipped_time')) {
        return [{ name: 'id' }, { name: 'cages_track_record_id' }, { name: 'cage_number' }, { name: 'tipped_time' }] as never
      }

      return [{ name: 'id' }] as never
    })

    await initLocalSchema()

    const alterCalls = vi.mocked(run).mock.calls.map((call) => call[0])
    expect(alterCalls).toContain('ALTER TABLE cages_tipped_time ADD COLUMN tipped_hour INTEGER')
    expect(alterCalls).toContain('ALTER TABLE cages_tipped_time ADD COLUMN checked_cage_numbers TEXT')
    expect(alterCalls).toContain('ALTER TABLE cages_tipped_time ADD COLUMN total_cages INTEGER')
    expect(alterCalls).toContain('ALTER TABLE cages_tipped_time ADD COLUMN cages_remain INTEGER')
  })

  it('does not re-add a column that PRAGMA table_info already reports as present', async () => {
    vi.mocked(query).mockResolvedValue([
      { name: 'id' },
      { name: 'tippler_start_time' },
      { name: 'tippler_stop_time' },
      { name: 'cages_out' },
      { name: 'cages_tipped' },
      { name: 'note' },
      { name: 'server_id' },
    ] as never)

    await initLocalSchema()

    const alterCalls = vi.mocked(run).mock.calls.map((call) => call[0])
    expect(alterCalls.some((sql) => sql.includes('ALTER TABLE cages_track_record'))).toBe(false)
  })
})

/**
 * initLocalSchema() — v7 station.icon column migration (Mills Setting
 * feature, entity-catalog v7). Same "CREATE TABLE IF NOT EXISTS is a no-op
 * on an existing table" gap as every migration above — a device/browser
 * whose `station` table was already seeded (e.g. via
 * seedDefaultStationsIfNeeded() before this change shipped) never picks up
 * the new `icon` column, so any v7 read against it fails with "no such
 * column: icon".
 */
describe('localSchema — initLocalSchema() station.icon v7 migration', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(run).mockResolvedValue({ changes: 1 })
  })

  it('adds the icon column to station when the table pre-dates the v7 schema', async () => {
    vi.mocked(query).mockImplementation(async (sql: string) => {
      if (sql.includes('station')) {
        return [{ name: 'id' }, { name: 'business_unit_id' }, { name: 'name' }, { name: 'type' }, { name: 'is_active' }] as never
      }

      return [{ name: 'id' }] as never
    })

    await initLocalSchema()

    const alterCalls = vi.mocked(run).mock.calls.map((call) => call[0])
    expect(alterCalls).toContain('ALTER TABLE station ADD COLUMN icon TEXT')
  })

  it('does not re-add icon when PRAGMA table_info already reports it as present', async () => {
    vi.mocked(query).mockResolvedValue([
      { name: 'id' },
      { name: 'business_unit_id' },
      { name: 'production_line_id' },
      { name: 'name' },
      { name: 'type' },
      { name: 'is_active' },
      { name: 'icon' },
    ] as never)

    await initLocalSchema()

    const alterCalls = vi.mocked(run).mock.calls.map((call) => call[0])
    expect(alterCalls.some((sql) => sql.includes('ALTER TABLE station'))).toBe(false)
  })
})

/**
 * initLocalSchema() — v9 station.production_line_id column migration
 * (Production Line feature, entity-catalog v9). Same "CREATE TABLE IF NOT
 * EXISTS is a no-op on an existing table" gap as every migration above.
 */
describe('localSchema — initLocalSchema() station.production_line_id v9 migration', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(run).mockResolvedValue({ changes: 1 })
  })

  it('adds the production_line_id column to station when the table pre-dates the v9 schema', async () => {
    vi.mocked(query).mockImplementation(async (sql: string) => {
      if (sql.includes('station')) {
        return [
          { name: 'id' },
          { name: 'business_unit_id' },
          { name: 'name' },
          { name: 'type' },
          { name: 'is_active' },
          { name: 'icon' },
        ] as never
      }

      return [{ name: 'id' }] as never
    })

    await initLocalSchema()

    const alterCalls = vi.mocked(run).mock.calls.map((call) => call[0])
    expect(alterCalls).toContain('ALTER TABLE station ADD COLUMN production_line_id TEXT')
  })

  it('does not re-add production_line_id when PRAGMA table_info already reports it as present', async () => {
    vi.mocked(query).mockResolvedValue([
      { name: 'id' },
      { name: 'business_unit_id' },
      { name: 'production_line_id' },
      { name: 'name' },
      { name: 'type' },
      { name: 'is_active' },
      { name: 'icon' },
    ] as never)

    await initLocalSchema()

    const alterCalls = vi.mocked(run).mock.calls.map((call) => call[0])
    expect(alterCalls.some((sql) => sql.includes('ALTER TABLE station'))).toBe(false)
  })
})

/**
 * initLocalSchema() — one-time station-doubling cleanup (2026-08-20). See
 * dedupeStationRows()'s own doc comment in localSchema.ts for the full
 * root-cause writeup (legacy synthetic seed + real Production Line sync
 * coexisting for the same (business_unit_id, type) rendered as doubled
 * tiles).
 */
describe('localSchema — initLocalSchema() station doubling cleanup (dedupeStationRows)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(run).mockResolvedValue({ changes: 1 })
    vi.mocked(query).mockResolvedValue([
      { name: 'id' },
      { name: 'business_unit_id' },
      { name: 'production_line_id' },
      { name: 'name' },
      { name: 'type' },
      { name: 'is_active' },
      { name: 'icon' },
    ] as never)
  })

  it('runs a DELETE that only removes legacy synthetic rows (production_line_id IS NULL) coexisting with a real row of the same type', async () => {
    await initLocalSchema()

    const deleteCalls = vi.mocked(run).mock.calls.filter((call) =>
      typeof call[0] === 'string' && call[0].includes('DELETE FROM station') && call[0].includes('production_line_id IS NULL'),
    )

    expect(deleteCalls).toHaveLength(1)
    const [sql] = deleteCalls[0]
    expect(sql).toContain('production_line_id IS NOT NULL')
  })
})

/**
 * fetchAndCacheMillSetting() — Mills Setting feature. Unlike the seed/
 * migration functions above, this makes a real GET /api/mill-settings/current
 * call (mill-setting is server-authored, not fixed local domain data) and
 * upserts the result into the local `mill_setting` table.
 */
describe('localSchema — fetchAndCacheMillSetting()', () => {
  const BUSINESS_UNIT_ID = 'bu-1'

  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(run).mockResolvedValue({ changes: 1 })
  })

  it('upserts the fetched mill-setting into the local table, keyed by business_unit_id', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        business_unit_id: BUSINESS_UNIT_ID,
        app_name: 'Mill A',
        logo: 'storage/logo.png',
        home_page_image: 'storage/home.png',
        jumlah_cages: 10,
      },
    })

    await fetchAndCacheMillSetting(BUSINESS_UNIT_ID)

    expect(apiClient.get).toHaveBeenCalledWith('/api/mill-settings/current')
    expect(run).toHaveBeenCalledWith(
      expect.stringContaining('INSERT INTO mill_setting'),
      expect.arrayContaining([expect.stringContaining(BUSINESS_UNIT_ID), BUSINESS_UNIT_ID, 'Mill A', 'storage/logo.png', 'storage/home.png', 10]),
    )
  })

  it('propagates a fetch failure to the caller (best-effort handling belongs to the caller, e.g. auth store login())', async () => {
    vi.mocked(apiClient.get).mockRejectedValue(new Error('network error'))

    await expect(fetchAndCacheMillSetting(BUSINESS_UNIT_ID)).rejects.toThrow('network error')
    expect(run).not.toHaveBeenCalled()
  })
})

/**
 * fetchAndCacheMillSetting() also syncs per-station icon overrides (Mills
 * Setting feature follow-up, 2026-08-19) via GET
 * /api/mill-settings/current/stations, matching by
 * (business_unit_id, type) rather than by id — see
 * fetchAndCacheStationIconOverrides()'s doc comment in localSchema.ts for
 * the known limitation this is a pragmatic, explicitly-accepted tradeoff
 * for (breaks if a mill ever has >1 active station of the same type).
 */
describe('localSchema — fetchAndCacheMillSetting() station icon override sync', () => {
  const BUSINESS_UNIT_ID = 'bu-1'

  function mockMillSettingThenStations(stations: Array<{ id: string; name: string; type: string; icon: string | null }>) {
    vi.mocked(apiClient.get).mockImplementation(async (url: string) => {
      if (url === '/api/mill-settings/current') {
        return {
          data: {
            business_unit_id: BUSINESS_UNIT_ID,
            app_name: 'Mill A',
            logo: null,
            home_page_image: null,
            jumlah_cages: 10,
          },
        }
      }

      if (url === '/api/mill-settings/current/stations') {
        return { data: { data: stations } }
      }

      throw new Error(`unexpected apiClient.get url in test: ${url}`)
    })
  }

  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(run).mockResolvedValue({ changes: 1 })
  })

  it('fetches the station list for the business unit after caching mill-setting', async () => {
    mockMillSettingThenStations([])

    await fetchAndCacheMillSetting(BUSINESS_UNIT_ID)

    expect(apiClient.get).toHaveBeenCalledWith('/api/mill-settings/current/stations')
  })

  it('updates the matching local station row(s) icon by (business_unit_id, type)', async () => {
    mockMillSettingThenStations([
      { id: 'server-station-1', name: 'Weighbridge', type: 'weighbridge', icon: 'truck' },
      { id: 'server-station-2', name: 'Grading', type: 'grading', icon: null },
    ])

    await fetchAndCacheMillSetting(BUSINESS_UNIT_ID)

    const updateCalls = vi.mocked(run).mock.calls.filter((call) => (call[0] as string).includes('UPDATE station SET icon'))
    expect(updateCalls).toHaveLength(2)
    expect(updateCalls).toContainEqual([
      'UPDATE station SET icon = ? WHERE business_unit_id = ? AND type = ?',
      ['truck', BUSINESS_UNIT_ID, 'weighbridge'],
    ])
    expect(updateCalls).toContainEqual([
      'UPDATE station SET icon = ? WHERE business_unit_id = ? AND type = ?',
      [null, BUSINESS_UNIT_ID, 'grading'],
    ])
  })

  it('does not run any station UPDATE when the business unit has no stations yet', async () => {
    mockMillSettingThenStations([])

    await fetchAndCacheMillSetting(BUSINESS_UNIT_ID)

    const updateCalls = vi.mocked(run).mock.calls.filter((call) => (call[0] as string).includes('UPDATE station SET icon'))
    expect(updateCalls).toHaveLength(0)
  })
})
