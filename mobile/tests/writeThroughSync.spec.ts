/**
 * writeThroughSync.spec.ts — "kirim data langsung ke server saat disimpan"
 * (product decision 2026-09-14).
 *
 * The contract that matters here is the SILENCE: a save is already durable
 * in local SQLite by the time this runs, so no failure mode of the push may
 * ever look like a save failure to the caller. Offline, feature off, server
 * rejection — all of them just mean the record waits for the next manual
 * sync, exactly as it did before this feature existed.
 *
 * localDb/apiClient are mocked at module level per this suite's convention.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/services/localDb', () => ({ query: vi.fn(), run: vi.fn() }))
vi.mock('@/services/apiClient', () => ({ default: { post: vi.fn(), get: vi.fn() } }))

import apiClient from '@/services/apiClient'
import { query, run } from '@/services/localDb'
import { isImmediateSyncEnabled, syncAfterSave } from '@/services/writeThroughSync'
import { pushSavedRecordNow } from '@/services/syncService'
import { useAuthStore } from '@/stores/auth'

const BUSINESS_UNIT_ID = 'bu-1'

function signIn() {
  const auth = useAuthStore()
  auth.user = {
    id: 'u-1',
    username: 'operator01',
    name: 'Operator Satu',
    role: 'operator',
    business_unit_id: BUSINESS_UNIT_ID,
  }
}

/** Projects `row` onto the column list of a `SELECT a, b, c FROM ...` statement. */
function pickSelectedColumns(sql: string, row: Record<string, unknown>): Record<string, unknown> {
  const match = /SELECT\s+([\s\S]+?)\s+FROM/i.exec(sql)
  if (!match || match[1].trim() === '*') return row
  const columns = match[1].split(',').map((c) => c.trim().split(/\s+/).pop() as string)
  return Object.fromEntries(columns.filter((c) => c in row).map((c) => [c, row[c]]))
}

/** Local reads the push path makes, in order: the record, its station, its details. */
function mockLocalReads({ immediateSync = true, hasRecord = true, productionLine = 'pl-1' as string | null } = {}) {
  vi.mocked(query).mockImplementation(async (sql: string) => {
    if (sql.includes('FROM mill_setting')) {
      // Column-faithful: return ONLY the columns the SELECT actually names,
      // like real SQLite would. The previous version returned the full row
      // regardless of the SQL text, which hid a SELECT that omitted
      // immediate_sync_enabled (audit 2026-10-04 — write-through never ran).
      const fullRow = { id: 'ms-1', business_unit_id: BUSINESS_UNIT_ID, app_name: null, logo: null, home_page_image: null, jumlah_cages: null, immediate_sync_enabled: immediateSync ? 1 : 0 }
      return [pickSelectedColumns(sql, fullRow)] as never
    }
    if (sql.includes('FROM threshing_record')) {
      return (hasRecord
        ? [{ id: 'local-1', station_id: 'st-1', thresher_id: 'TH-001', date: '2026-09-14', note: null, checked_by: null, acknowledged_by: null }]
        : []) as never
    }
    if (sql.includes('FROM station')) {
      return [{ production_line_id: productionLine }] as never
    }
    if (sql.includes('FROM threshing_detail')) {
      return [{ time_slot: '07:00' }] as never
    }
    return [] as never
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  setActivePinia(createPinia())
  signIn()
})

describe('isImmediateSyncEnabled()', () => {
  it('is off by default so no mill changes behaviour without opting in', async () => {
    mockLocalReads({ immediateSync: false })
    expect(await isImmediateSyncEnabled()).toBe(false)
  })

  it('is on once the mill setting says so', async () => {
    mockLocalReads({ immediateSync: true })
    expect(await isImmediateSyncEnabled()).toBe(true)
  })

  it('is off when no mill setting has been cached yet (first run, offline)', async () => {
    vi.mocked(query).mockResolvedValue([])
    expect(await isImmediateSyncEnabled()).toBe(false)
  })
})

describe('syncAfterSave()', () => {
  it('pushes the record and marks it synced when the feature is on', async () => {
    mockLocalReads({ immediateSync: true })
    vi.mocked(apiClient.post).mockResolvedValue({ data: { id: 'server-1' } })

    const outcome = await syncAfterSave('threshing_record', 'local-1')

    expect(outcome).toEqual({ synced: true, rejection: null })
    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/threshing-records',
      expect.objectContaining({ production_line_id: 'pl-1', thresher_id: 'TH-001' }),
    )
    expect(run).toHaveBeenCalledWith(
      `UPDATE threshing_record SET status = 'synced', server_id = ?, sync_error = NULL WHERE id = ?`,
      ['server-1', 'local-1'],
    )
  })

  it('does nothing at all when the feature is off', async () => {
    mockLocalReads({ immediateSync: false })

    expect(await syncAfterSave('threshing_record', 'local-1')).toEqual({ synced: false, rejection: null })
    expect(apiClient.post).not.toHaveBeenCalled()
  })

  it('stays silent when the device is offline — the record just waits for the next sync', async () => {
    mockLocalReads({ immediateSync: true })
    vi.mocked(apiClient.post).mockRejectedValue({ message: 'Tidak dapat terhubung ke server.' })

    // No throw, and no 'synced' write: the row keeps status 'saved'.
    expect(await syncAfterSave('threshing_record', 'local-1')).toEqual({ synced: false, rejection: null })
    expect(run).not.toHaveBeenCalledWith(expect.stringContaining("status = 'synced'"), expect.anything())
  })

  it('stays silent when the record cannot be traced to a production line', async () => {
    mockLocalReads({ immediateSync: true, productionLine: null })

    expect(await syncAfterSave('threshing_record', 'local-1')).toEqual({ synced: false, rejection: null })
    expect(apiClient.post).not.toHaveBeenCalled()
  })

  // 2026-10-03 — a REJECTION is not a transient failure. 422 PERIOD_CLOSED
  // (usecase-141) is refused again on every retry, so the caller gets the
  // server's message to show; the row still stays 'saved' locally.
  it('reports the server message when the push is rejected with 422 PERIOD_CLOSED', async () => {
    mockLocalReads({ immediateSync: true })
    const message = 'Periode "September" untuk stasiun ini sudah ditutup, sehingga data bertanggal 2026-09-14 tidak dapat disimpan atau diubah.'
    vi.mocked(apiClient.post).mockRejectedValue({ status: 422, message })

    expect(await syncAfterSave('threshing_record', 'local-1')).toEqual({ synced: false, rejection: message })
    expect(run).not.toHaveBeenCalledWith(expect.stringContaining("status = 'synced'"), expect.anything())
  })

  // Audit 2026-10-05: 401 = SESI yang ditolak (akun dinonaktifkan), bukan
  // record. Interceptor apiClient membawa ke Login; record terkirim normal
  // setelah login ulang — jangan dilaporkan sebagai penolakan dan jangan
  // tinggalkan sync_error "Unauthenticated" di record.
  it('stays silent on a 401 and does not remember it as the record\'s sync_error', async () => {
    mockLocalReads({ immediateSync: true })
    vi.mocked(apiClient.post).mockRejectedValue({ status: 401, message: 'Unauthenticated.' })

    expect(await syncAfterSave('threshing_record', 'local-1')).toEqual({ synced: false, rejection: null })
    expect(run).not.toHaveBeenCalledWith(expect.stringContaining('sync_error = ?'), expect.anything())
    expect(run).not.toHaveBeenCalledWith(expect.stringContaining("status = 'synced'"), expect.anything())
  })

  it('stays silent on a 5xx — a server fault may well succeed on the next sync', async () => {
    mockLocalReads({ immediateSync: true })
    vi.mocked(apiClient.post).mockRejectedValue({ status: 503, message: 'Server sedang sibuk.' })

    expect(await syncAfterSave('threshing_record', 'local-1')).toEqual({ synced: false, rejection: null })
  })
})

describe('pushSavedRecordNow()', () => {
  it('ignores a station with no push path instead of throwing', async () => {
    expect(await pushSavedRecordNow('not_a_station_record', 'local-1')).toBeNull()
  })

  it('ignores a record that is not in a pushable state', async () => {
    // The query filters on status = 'saved', so a draft simply returns nothing.
    mockLocalReads({ hasRecord: false })

    expect(await pushSavedRecordNow('threshing_record', 'local-1')).toBeNull()
    expect(apiClient.post).not.toHaveBeenCalled()
  })

  it('covers a station that previously had no sync path at all (boiler room)', async () => {
    vi.mocked(query).mockImplementation(async (sql: string) => {
      if (sql.includes('FROM boiler_room_record')) {
        return [{ id: 'local-br', station_id: 'st-9', boiler_room_id: 'BR-001', date: '2026-09-14', note: null, checked_by: null, acknowledged_by: null }] as never
      }
      if (sql.includes('FROM station')) return [{ production_line_id: 'pl-1' }] as never
      return [] as never
    })
    vi.mocked(apiClient.post).mockResolvedValue({ data: { id: 'server-br' } })

    const result = await pushSavedRecordNow('boiler_room_record', 'local-br')

    expect(result).toEqual({ id: 'local-br', label: 'BR-001', ok: true })
    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/boiler-room-records',
      expect.objectContaining({ boiler_room_id: 'BR-001' }),
    )
  })
})
