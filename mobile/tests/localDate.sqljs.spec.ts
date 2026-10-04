/**
 * localDate.sqljs.spec.ts — audit 2026-10-04 (KRITIS #4): draft dates were
 * stored as UTC ISO (`new Date().toISOString()`), so a draft created between
 * 00:00 and 06:59 WIB got YESTERDAY's date: not counted in "Hari Ini",
 * hidden by Data Preview's default date filter, and sent to the server with
 * the wrong date.
 *
 * Runs against a REAL SQLite (sql.js) with the real localSchema DDL, with
 * the process clock pinned to 01:30 WIB — the exact window that broke.
 */
process.env.TZ = 'Asia/Jakarta'

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/localDb', async () => (await import('./support/sqlJsLocalDb')).localDbMock)

import { query, run, resetSqlJsDb } from './support/sqlJsLocalDb'
import { initLocalSchema } from '@/services/localSchema'
import {
  nowLocalDateTimeString,
  toDateInputValue,
  toDateTimeLocalInputValue,
  toLocalDateString,
  toOffsetDateTime,
  todayLocalDateString,
} from '@/utils/localDate'

import * as boilerRoom from '@/services/boilerRoomRecordRepo'
import * as clarification from '@/services/clarificationRecordRepo'
import * as cpoDispatch from '@/services/cpoDispatchRecordRepo'
import * as depricarping from '@/services/depricarpingRecordRepo'
import * as effluentPlant from '@/services/effluentPlantRecordRepo'
import * as engineRoom from '@/services/engineRoomRecordRepo'
import * as kernelDispatch from '@/services/kernelDispatchRecordRepo'
import * as kernelPlant from '@/services/kernelPlantRecordRepo'
import * as pressing from '@/services/pressingRecordRepo'
import * as processQualityControl from '@/services/processQualityControlRecordRepo'
import * as processWater from '@/services/processWaterRecordRepo'
import * as solidWasteDisposal from '@/services/solidWasteDisposalRecordRepo'
import * as sterilizer from '@/services/sterilizerRecordRepo'
import * as storageTank from '@/services/storageTankRecordRepo'
import * as threshing from '@/services/threshingRecordRepo'
import * as cagesTrack from '@/services/cagesTrackRecordRepo'

const USER = 'user-1'
// 01:30 WIB on 4 Oct 2026 == 18:30 UTC on 3 Oct 2026.
const EARLY_MORNING_WIB = new Date('2026-10-03T18:30:00.000Z')

const DATE_REPOS: Array<[string, string, { createDraft: (userId: string) => Promise<string> }]> = [
  ['threshing_record', 'threshing', threshing],
  ['pressing_record', 'pressing', pressing],
  ['depricarping_record', 'depricarping', depricarping],
  ['kernel_plant_record', 'kernelPlant', kernelPlant],
  ['boiler_room_record', 'boilerRoom', boilerRoom],
  ['clarification_record', 'clarification', clarification],
  ['engine_room_record', 'engineRoom', engineRoom],
  ['effluent_plant_record', 'effluentPlant', effluentPlant],
  ['process_water_record', 'processWater', processWater],
  ['process_quality_control_record', 'processQualityControl', processQualityControl],
  ['storage_tank_record', 'storageTank', storageTank],
  ['sterilizer_record', 'sterilizer', sterilizer],
  ['cpo_dispatch_record', 'cpoDispatch', cpoDispatch],
  ['kernel_dispatch_record', 'kernelDispatch', kernelDispatch],
  ['solid_waste_disposal_record', 'solidWasteDisposal', solidWasteDisposal],
]

beforeEach(async () => {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(EARLY_MORNING_WIB)
  await resetSqlJsDb()
  await initLocalSchema()
})

afterEach(() => {
  vi.useRealTimers()
})

describe('createDraft() stores the LOCAL date (01:30 WIB → today, not yesterday)', () => {
  it.each(DATE_REPOS)('%s', async (table, _name, repo) => {
    const id = await repo.createDraft(USER)

    const rows = await query<{ date: string }>(`SELECT date FROM ${table} WHERE id = ?`, [id])
    expect(rows[0].date).toBe('2026-10-04')
  })

  it('threshing "Hari Ini" counter counts a draft created at 01:30 WIB', async () => {
    await threshing.createDraft(USER)
    const summary = await threshing.getTodaySummary(USER)
    expect(Object.values(summary).some((value) => value === 1)).toBe(true)
  })

  it('cages_track_record.tippler_start_time is local wall-clock time without a zone suffix', async () => {
    const id = await cagesTrack.createDraft(USER)
    const rows = await query<{ tippler_start_time: string }>(
      'SELECT tippler_start_time FROM cages_track_record WHERE id = ?',
      [id],
    )
    expect(rows[0].tippler_start_time).toBe('2026-10-04T01:30:00')
  })
})

describe('legacy UTC values are normalised to local on schema init', () => {
  it('converts a stored ISO-UTC draft date and weighbridge datetime, leaves local values alone', async () => {
    await run(`INSERT INTO threshing_record (id, status, created_by, date, created_at, updated_at) VALUES ('legacy', 'saved', ?, ?, 'x', 'x')`, [
      USER,
      '2026-10-03T18:30:00.000Z',
    ])
    await run(`INSERT INTO threshing_record (id, status, created_by, date, created_at, updated_at) VALUES ('fresh', 'saved', ?, ?, 'x', 'x')`, [
      USER,
      '2026-10-02',
    ])
    await run(
      `INSERT INTO weighbridge_record (id, status, created_by, record_datetime, created_at, updated_at) VALUES ('wb-legacy', 'saved', ?, ?, 'x', 'x')`,
      [USER, '2026-10-03T18:30:00.000Z'],
    )

    await initLocalSchema()

    const threshingRows = await query<{ id: string; date: string }>('SELECT id, date FROM threshing_record ORDER BY id')
    expect(threshingRows).toEqual([
      { id: 'fresh', date: '2026-10-02' },
      { id: 'legacy', date: '2026-10-04' },
    ])
    const wb = await query<{ record_datetime: string }>(`SELECT record_datetime FROM weighbridge_record`)
    expect(wb[0].record_datetime).toBe('2026-10-04T01:30:00')
  })
})

describe('localDate helpers', () => {
  it('today / now are local', () => {
    expect(todayLocalDateString()).toBe('2026-10-04')
    expect(nowLocalDateTimeString()).toBe('2026-10-04T01:30:00')
  })

  it('normalises stored values for date / datetime-local inputs', () => {
    expect(toLocalDateString('2026-10-03T18:30:00.000Z')).toBe('2026-10-04')
    expect(toLocalDateString('2026-10-04T01:30:00')).toBe('2026-10-04')
    expect(toLocalDateString('2026-10-04')).toBe('2026-10-04')
    expect(toLocalDateString(null)).toBeNull()
    // The bug: an ISO UTC string bound to <input type="datetime-local">
    // renders empty. The normalised value is what the input accepts.
    expect(toDateTimeLocalInputValue('2026-10-03T18:30:00.000Z')).toBe('2026-10-04T01:30')
    expect(toDateTimeLocalInputValue('')).toBe('')
    expect(toDateInputValue('2026-10-03T18:30:00.000Z')).toBe('2026-10-04')
  })

  it('server payload datetimes carry the explicit device offset', () => {
    expect(toOffsetDateTime('2026-10-04T01:30:00')).toBe('2026-10-04T01:30:00+07:00')
    expect(toOffsetDateTime('2026-10-03T18:30:00.000Z')).toBe('2026-10-04T01:30:00+07:00')
    expect(toOffsetDateTime(null)).toBeNull()
  })
})
