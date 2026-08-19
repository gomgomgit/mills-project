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

import { query, run } from '@/services/localDb'
import { initLocalSchema, seedDefaultStationsIfNeeded } from '@/services/localSchema'

const BUSINESS_UNIT_ID = 'bu-1'

describe('localSchema — seedDefaultStationsIfNeeded()', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(run).mockResolvedValue({ changes: 1 })
  })

  it('seeds all 15 MVP stations (3 active + 12 placeholder) for the given business unit', async () => {
    await seedDefaultStationsIfNeeded(BUSINESS_UNIT_ID)

    expect(run).toHaveBeenCalledTimes(15)
  })

  it('uses INSERT OR IGNORE so re-seeding on every login never duplicates rows', async () => {
    await seedDefaultStationsIfNeeded(BUSINESS_UNIT_ID)

    for (const call of vi.mocked(run).mock.calls) {
      expect(call[0]).toContain('INSERT OR IGNORE INTO station')
    }
  })

  it('seeds exactly the 3 active MVP station types with is_active=1', async () => {
    await seedDefaultStationsIfNeeded(BUSINESS_UNIT_ID)

    const activeCalls = vi.mocked(run).mock.calls.filter((call) => call[1]?.[4] === 1)
    const activeTypes = activeCalls.map((call) => call[1]?.[3])

    expect(activeTypes.sort()).toEqual(['cages-track', 'grading', 'weighbridge'])
  })

  it('seeds the 12 placeholder stations with type=other and is_active=0', async () => {
    await seedDefaultStationsIfNeeded(BUSINESS_UNIT_ID)

    const placeholderCalls = vi.mocked(run).mock.calls.filter((call) => call[1]?.[4] === 0)

    expect(placeholderCalls).toHaveLength(12)
    for (const call of placeholderCalls) {
      expect(call[1]?.[3]).toBe('other')
    }
  })

  it('scopes every seeded row to the given business_unit_id and derives a deterministic id from it', async () => {
    await seedDefaultStationsIfNeeded(BUSINESS_UNIT_ID)

    for (const call of vi.mocked(run).mock.calls) {
      const [id, businessUnitId] = call[1] ?? []
      expect(businessUnitId).toBe(BUSINESS_UNIT_ID)
      expect(id).toContain(BUSINESS_UNIT_ID)
    }
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
    ] as never)

    await initLocalSchema()

    const alterCalls = vi.mocked(run).mock.calls.map((call) => call[0])
    expect(alterCalls.some((sql) => sql.includes('ALTER TABLE cages_track_record'))).toBe(false)
  })
})
