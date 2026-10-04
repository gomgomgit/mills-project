/**
 * syncService.sqljs.spec.ts — audit 2026-10-04 regressions, run against a
 * REAL SQLite (sql.js) with the real localSchema DDL so the actual SQL text
 * is exercised (only the HTTP layer is mocked):
 *
 *   #3 KRITIS  Grading sync sent fake `default-grading-parameter-N` ids →
 *              server 500. Now remapped to real ids by name.
 *   #5 KRITIS  Manual sync pushed every record to the CURRENTLY selected
 *              Production Line. Now each record goes to its own station's line.
 *   #9 KECIL   A rejected record left no trace; now `sync_error` is stored
 *              (cleared on success) and results carry the station name.
 *   #4 KRITIS  Payload dates: local date-only, datetimes with explicit offset.
 *   #6 SEDANG  pullVerificationStatus() mirrors server verification locally.
 *   #7 SEDANG  Grading's WB Card dropdown excludes empty/abandoned drafts.
 */
process.env.TZ = 'Asia/Jakarta'

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/services/localDb', async () => (await import('./support/sqlJsLocalDb')).localDbMock)
vi.mock('@/services/apiClient', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() } }))

import apiClient from '@/services/apiClient'
import { query, run, resetSqlJsDb } from './support/sqlJsLocalDb'
import { initLocalSchema, seedGradingParametersIfNeeded } from '@/services/localSchema'
import { pushSavedRecordNow, syncAllRecords } from '@/services/syncService'
import { fetchAndCacheGradingParameters } from '@/services/gradingParameterSync'
import { pullVerificationStatus, resetVerificationPullSupport } from '@/services/recordVerificationApi'
import { getWeighbridgeRecordOptions } from '@/services/gradingRecordRepo'
import { useAuthStore } from '@/stores/auth'

const USER = 'user-1'
const NOW = '2026-10-04T08:00:00.000Z'

const SERVER_PARAMS = [
  { id: '6f1c2b8e-0000-4000-8000-000000000001', name: 'Mentah', uom: 'bunch', sort_order: 1 },
  { id: '6f1c2b8e-0000-4000-8000-000000000003', name: 'Masak', uom: 'bunch', sort_order: 3 },
]

async function station(id: string, type: string, productionLineId: string | null, name = type) {
  await run(
    `INSERT INTO station (id, business_unit_id, production_line_id, name, type, is_active, created_at, updated_at)
     VALUES (?, 'bu-1', ?, ?, ?, 1, ?, ?)`,
    [id, productionLineId, name, type, NOW, NOW],
  )
}

async function threshingRecord(id: string, stationId: string | null, date = '2026-10-04') {
  await run(
    `INSERT INTO threshing_record (id, station_id, thresher_id, date, status, created_by, created_at, updated_at)
     VALUES (?, ?, ?, ?, 'saved', ?, ?, ?)`,
    [id, stationId, `TH-${id}`, date, USER, NOW, NOW],
  )
}

function postedTo(endpoint: string) {
  return vi
    .mocked(apiClient.post)
    .mock.calls.filter(([url]) => url === endpoint)
    .map(([, body]) => body as Record<string, unknown>)
}

beforeEach(async () => {
  vi.clearAllMocks()
  resetVerificationPullSupport()
  setActivePinia(createPinia())
  useAuthStore().user = { id: USER, username: 'op', name: 'Op', role: 'operator', business_unit_id: 'bu-1' }
  await resetSqlJsDb()
  await initLocalSchema()
  vi.mocked(apiClient.get).mockRejectedValue({ message: 'not found', status: 404 })
  let n = 0
  vi.mocked(apiClient.post).mockImplementation(async () => ({ data: { id: `server-${++n}` } }))
})

describe('#5 — each record syncs to the Production Line of the station it was created on', () => {
  it('uses the record station line, falling back to the selected line only when there is no station_id', async () => {
    await station('st-th-line1', 'threshing', 'line-1', 'Threshing Line 1')
    await station('st-th-line2', 'threshing', 'line-2', 'Threshing Line 2')
    await threshingRecord('a', 'st-th-line1')
    await threshingRecord('b', 'st-th-line2')
    await threshingRecord('c', null)

    // Operator currently has Line 2 selected on Station List.
    const summary = await syncAllRecords('line-2')

    const lineByThresher = Object.fromEntries(
      postedTo('/api/threshing-records').map((body) => [body.thresher_id, body.production_line_id]),
    )
    expect(lineByThresher).toEqual({ 'TH-a': 'line-1', 'TH-b': 'line-2', 'TH-c': 'line-2' })
    expect(summary.byStation.threshing.map((item) => [item.id, item.productionLineId])).toEqual(
      expect.arrayContaining([
        ['a', 'line-1'],
        ['b', 'line-2'],
        ['c', 'line-2'],
      ]),
    )
  })

  it('without a selected line, a record without a station fails locally with a clear reason (no POST)', async () => {
    await threshingRecord('orphan', null)

    const summary = await syncAllRecords(null)

    expect(postedTo('/api/threshing-records')).toHaveLength(0)
    expect(summary.byStation.threshing[0]).toMatchObject({ ok: false, reason: expect.stringMatching(/Production Line/) })
  })
})

describe('#9 — rejection reason is remembered on the record and the result names the station', () => {
  it('stores sync_error on 422, keeps status saved, and clears it once a later sync succeeds', async () => {
    await station('st-th', 'threshing', 'line-1', 'Threshing 01')
    await threshingRecord('r1', 'st-th')
    vi.mocked(apiClient.post).mockRejectedValueOnce({ status: 422, message: 'Periode sudah ditutup.' })

    const failed = await syncAllRecords('line-1')

    expect(failed.items[0]).toMatchObject({ ok: false, stationName: 'Threshing 01', reason: 'Periode sudah ditutup.' })
    let rows = await query<{ status: string; sync_error: string | null }>(`SELECT status, sync_error FROM threshing_record`)
    expect(rows[0]).toEqual({ status: 'saved', sync_error: 'Periode sudah ditutup.' })

    await syncAllRecords('line-1')
    rows = await query(`SELECT status, sync_error FROM threshing_record`)
    expect(rows[0]).toEqual({ status: 'synced', sync_error: null })
  })

  it('does NOT store an offline failure as a rejection', async () => {
    await threshingRecord('r1', null)
    vi.mocked(apiClient.post).mockRejectedValueOnce({ message: 'Tidak dapat terhubung', network: true })

    await syncAllRecords('line-1')

    const rows = await query<{ sync_error: string | null }>(`SELECT sync_error FROM threshing_record`)
    expect(rows[0].sync_error).toBeNull()
  })
})

describe('#4 — payload dates', () => {
  it('sends a local date-only `date` even for a legacy UTC-ISO row, and weighbridge datetime with +07:00', async () => {
    await threshingRecord('legacy', null, '2026-10-03T18:30:00.000Z')
    await run(
      `INSERT INTO weighbridge_record (id, wb_card_number, weighbridge_type, record_datetime, status, created_by, created_at, updated_at)
       VALUES ('wb', 'WB-1', 'receive', '2026-10-04T01:30:00', 'saved', ?, ?, ?)`,
      [USER, NOW, NOW],
    )

    await syncAllRecords('line-1')

    expect(postedTo('/api/threshing-records')[0].date).toBe('2026-10-04')
    expect(postedTo('/api/weighbridge-records')[0].record_datetime).toBe('2026-10-04T01:30:00+07:00')
  })
})

describe('#3 — grading parameters: fake local ids are never sent to the server', () => {
  async function gradingWithFakeParameter() {
    await seedGradingParametersIfNeeded()
    await run(
      `INSERT INTO weighbridge_record (id, wb_card_number, status, server_id, created_by, created_at, updated_at)
       VALUES ('wb-1', 'WB-1', 'synced', 'server-wb-1', ?, ?, ?)`,
      [USER, NOW, NOW],
    )
    await run(
      `INSERT INTO grading_record (id, grading_number, date, weighbridge_record_id, netto, quantity, status, created_by, created_at, updated_at)
       VALUES ('gr-1', 'GR-1', '2026-10-04T07:00:00', 'wb-1', 1000, 50, 'saved', ?, ?, ?)`,
      [USER, NOW, NOW],
    )
    // Index 1 = 'Mentah', index 3 = 'Masak' in DEFAULT_GRADING_PARAMETERS.
    for (const [id, param] of [['d1', 'default-grading-parameter-1'], ['d2', 'default-grading-parameter-3']]) {
      await run(
        `INSERT INTO grading_detail (id, grading_record_id, grading_parameter_id, quantity, created_at, updated_at)
         VALUES (?, 'gr-1', ?, 10, ?, ?)`,
        [id, param, NOW, NOW],
      )
    }
  }

  it('maps fake ids to the server ids by name (master fetched before the push) and remaps local details', async () => {
    await gradingWithFakeParameter()
    vi.mocked(apiClient.get).mockImplementation(async (url: string) => {
      if (url === '/api/grading-parameters') return { data: { data: SERVER_PARAMS } }
      throw { status: 404, message: 'nope' }
    })

    const summary = await syncAllRecords('line-1')

    expect(summary.byStation.grading[0]).toMatchObject({ ok: true })
    const body = postedTo('/api/grading-records')[0]
    expect(body.details).toEqual([
      { grading_parameter_id: SERVER_PARAMS[0].id, quantity: 10 },
      { grading_parameter_id: SERVER_PARAMS[1].id, quantity: 10 },
    ])
    expect(body.date).toBe('2026-10-04')
    // Local details now point at the real ids; mapped fake rows are gone.
    const details = await query<{ grading_parameter_id: string }>(
      `SELECT grading_parameter_id FROM grading_detail ORDER BY id`,
    )
    expect(details.map((d) => d.grading_parameter_id)).toEqual([SERVER_PARAMS[0].id, SERVER_PARAMS[1].id])
    const fake = await query(`SELECT id FROM grading_parameter WHERE id IN ('default-grading-parameter-1', 'default-grading-parameter-3')`)
    expect(fake).toHaveLength(0)
  })

  it('when the master cannot be fetched, the record fails ON THE DEVICE with a clear reason — no POST with fake ids', async () => {
    await gradingWithFakeParameter()

    const summary = await syncAllRecords('line-1')

    expect(postedTo('/api/grading-records')).toHaveLength(0)
    expect(summary.byStation.grading[0]).toMatchObject({
      ok: false,
      stationName: 'Grading',
      reason: expect.stringMatching(/Master Quality Parameter/),
    })
    const rows = await query<{ sync_error: string }>(`SELECT sync_error FROM grading_record`)
    expect(rows[0].sync_error).toMatch(/Master Quality Parameter/)
  })

  it('the boot-time seed does not re-insert fake ids once the server master is cached', async () => {
    await seedGradingParametersIfNeeded()
    vi.mocked(apiClient.get).mockResolvedValue({ data: { data: SERVER_PARAMS } })
    await fetchAndCacheGradingParameters()
    await seedGradingParametersIfNeeded()

    const ids = await query<{ id: string }>(`SELECT id FROM grading_parameter WHERE id IN ('default-grading-parameter-1', 'default-grading-parameter-3')`)
    expect(ids).toHaveLength(0)
  })
})

describe('#6 — pullVerificationStatus() mirrors server-side verification into synced local rows', () => {
  it('updates checked_by/acknowledged_by (+names) for this user\'s synced records, and is silent offline', async () => {
    await run(
      `INSERT INTO threshing_record (id, thresher_id, status, server_id, created_by, created_at, updated_at)
       VALUES ('l1', 'TH-1', 'synced', 'srv-1', ?, ?, ?)`,
      [USER, NOW, NOW],
    )
    vi.mocked(apiClient.get).mockResolvedValueOnce({
      data: {
        data: [{ id: 'srv-1', checked_by: 'spv-1', checked_by_name: 'Supervisor Satu', acknowledged_by: null, acknowledged_by_name: null }],
      },
    })

    expect(await pullVerificationStatus('threshing', 'threshing_record', USER)).toBe(1)
    expect(apiClient.get).toHaveBeenCalledWith('/api/records/threshing/verification', expect.objectContaining({ params: { ids: ['srv-1'] } }))
    const rows = await query(`SELECT checked_by, checked_by_name FROM threshing_record`)
    expect(rows[0]).toEqual({ checked_by: 'spv-1', checked_by_name: 'Supervisor Satu' })

    vi.mocked(apiClient.get).mockRejectedValueOnce({ message: 'offline', network: true })
    expect(await pullVerificationStatus('threshing', 'threshing_record', USER)).toBe(0)
  })

  it('stops asking a server that has no such endpoint (404) for the rest of the session', async () => {
    await run(
      `INSERT INTO threshing_record (id, thresher_id, status, server_id, created_by, created_at, updated_at)
       VALUES ('l1', 'TH-1', 'synced', 'srv-1', ?, ?, ?)`,
      [USER, NOW, NOW],
    )
    vi.mocked(apiClient.get).mockRejectedValue({ message: 'Not Found', status: 404 })

    expect(await pullVerificationStatus('threshing', 'threshing_record', USER)).toBe(0)
    expect(await pullVerificationStatus('pressing', 'pressing_record', USER)).toBe(0)
    expect(apiClient.get).toHaveBeenCalledTimes(1)
  })
})

describe('#7 — Grading WB Card dropdown', () => {
  it('lists only saved/synced weighbridge records that have a WB Card number (plus an already-selected id)', async () => {
    const insert = (id: string, status: string, card: string | null) =>
      run(
        `INSERT INTO weighbridge_record (id, wb_card_number, record_datetime, status, created_by, created_at, updated_at)
         VALUES (?, ?, '2026-10-04T07:00:00', ?, ?, ?, ?)`,
        [id, card, status, USER, NOW, NOW],
      )
    await insert('empty-draft', 'draft_ongoing', null)
    await insert('paused-with-card', 'draft_paused', 'WB-P')
    await insert('saved', 'saved', 'WB-S')
    await insert('synced', 'synced', 'WB-Y')
    await insert('saved-blank', 'saved', '  ')

    expect((await getWeighbridgeRecordOptions()).map((o) => o.id).sort()).toEqual(['saved', 'synced'])
    expect((await getWeighbridgeRecordOptions('paused-with-card')).map((o) => o.id).sort()).toEqual([
      'paused-with-card',
      'saved',
      'synced',
    ])
  })
})

describe('Audit 2026-10-05 #4b — Sterilizer "Checked by SPV" hanya dikirim oleh Supervisor', () => {
  async function sterilizerRecordWithCheckedRow() {
    await station('st-ster', 'sterilizer', 'line-1', 'Sterilizer')
    await run(
      `INSERT INTO sterilizer_record (id, station_id, sterilizer_id, date, status, created_by, created_at, updated_at)
       VALUES ('ster-1', 'st-ster', 'ST-1', '2026-10-04', 'saved', ?, ?, ?)`,
      [USER, NOW, NOW],
    )
    // Nilai 1 di lokal (mis. record lama/impor) — operator tetap tidak boleh
    // mengirimnya ke server.
    await run(
      `INSERT INTO sterilizer_detail (id, sterilizer_record_id, sterilizer_no, close_door_time, checked_by_spv, remarks, created_at, updated_at)
       VALUES ('ster-d1', 'ster-1', '1', '07:00', 1, 'ok', ?, ?)`,
      [NOW, NOW],
    )
  }

  it('Operator: field checked_by_spv TIDAK ada di payload detail (bukan false, tidak dikirim sama sekali)', async () => {
    await sterilizerRecordWithCheckedRow()

    await syncAllRecords('line-1')

    const [body] = postedTo('/api/sterilizer-records')
    const details = body.details as Record<string, unknown>[]
    expect(details).toHaveLength(1)
    expect(details[0]).not.toHaveProperty('checked_by_spv')
    // Kolom lain tetap terkirim.
    expect(details[0]).toMatchObject({ sterilizer_no: '1', close_door_time: '07:00', remarks: 'ok' })
  })

  it('Supervisor: checked_by_spv tetap dikirim apa adanya', async () => {
    useAuthStore().user = { id: USER, username: 'spv', name: 'Spv', role: 'supervisor', business_unit_id: 'bu-1' }
    await sterilizerRecordWithCheckedRow()

    await syncAllRecords('line-1')

    const [body] = postedTo('/api/sterilizer-records')
    expect((body.details as Record<string, unknown>[])[0]).toHaveProperty('checked_by_spv', 1)
  })
})

describe('Audit 2026-10-05 — 401 di tengah batch menghentikan sinkronisasi', () => {
  async function pressingRecord(id: string) {
    await run(
      `INSERT INTO pressing_record (id, station_id, presser_id, date, status, created_by, created_at, updated_at)
       VALUES (?, NULL, ?, '2026-10-05', 'saved', ?, ?, ?)`,
      [id, `PR-${id}`, USER, NOW, NOW],
    )
  }

  async function allRows() {
    return [
      ...(await query<{ id: string; status: string; sync_error: string | null }>(`SELECT id, status, sync_error FROM threshing_record ORDER BY id`)),
      ...(await query<{ id: string; status: string; sync_error: string | null }>(`SELECT id, status, sync_error FROM pressing_record ORDER BY id`)),
    ]
  }

  it('berhenti pada 401 pertama: sisa record tidak dikirim, semua tetap saved tanpa sync_error', async () => {
    await threshingRecord('a', null)
    await threshingRecord('b', null)
    await threshingRecord('c', null)
    await pressingRecord('p')
    vi.mocked(apiClient.post).mockRejectedValue({ status: 401, message: 'Unauthenticated.' })

    const summary = await syncAllRecords('line-1')

    expect(apiClient.post).toHaveBeenCalledTimes(1)
    expect(summary.sessionExpired).toBe(true)
    expect(summary.syncedCount).toBe(0)
    expect(await allRows()).toEqual([
      { id: 'a', status: 'saved', sync_error: null },
      { id: 'b', status: 'saved', sync_error: null },
      { id: 'c', status: 'saved', sync_error: null },
      { id: 'p', status: 'saved', sync_error: null },
    ])
  })

  it('berhenti juga bila sesi lokal sudah dibersihkan interceptor (token berubah) di tengah batch', async () => {
    const auth = useAuthStore()
    auth.token = 'token-lama'
    await threshingRecord('a', null)
    await threshingRecord('b', null)
    await pressingRecord('p')
    let calls = 0
    vi.mocked(apiClient.post).mockImplementation(async () => {
      calls++
      if (calls === 1) return { data: { id: 'server-a' } }
      // Seperti interceptor apiClient: sesi dibersihkan lalu 401.
      auth.token = null
      throw { status: 401, message: 'Unauthenticated.' }
    })

    const summary = await syncAllRecords('line-1')

    expect(apiClient.post).toHaveBeenCalledTimes(2)
    expect(summary.sessionExpired).toBe(true)
    expect(await allRows()).toEqual([
      { id: 'a', status: 'synced', sync_error: null },
      { id: 'b', status: 'saved', sync_error: null },
      { id: 'p', status: 'saved', sync_error: null },
    ])
  })

  it('write-through (pushSavedRecordNow): 401 tidak menulis sync_error', async () => {
    await threshingRecord('w', null)
    vi.mocked(apiClient.post).mockRejectedValue({ status: 401, message: 'Unauthenticated.' })

    const result = await pushSavedRecordNow('threshing_record', 'w', 'line-1')

    expect(result).toMatchObject({ ok: false, status: 401 })
    expect(await query(`SELECT status, sync_error FROM threshing_record`)).toEqual([{ status: 'saved', sync_error: null }])
  })

  it('422 tetap dicatat sebagai sync_error dan batch tetap berlanjut', async () => {
    await threshingRecord('a', null)
    await threshingRecord('b', null)
    vi.mocked(apiClient.post).mockRejectedValueOnce({ status: 422, message: 'Periode sudah ditutup.' })

    const summary = await syncAllRecords('line-1')

    expect(apiClient.post).toHaveBeenCalledTimes(2)
    expect(summary.sessionExpired).toBe(false)
    expect((await allRows()).map((row) => row.sync_error).filter(Boolean)).toEqual(['Periode sudah ditutup.'])
  })
})
