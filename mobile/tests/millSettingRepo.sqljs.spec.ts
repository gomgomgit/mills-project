/**
 * millSettingRepo.sqljs.spec.ts — round-trip through a REAL SQLite (sql.js)
 * using the real localSchema DDL: fetchAndCacheMillSetting() writes the row,
 * millSettingRepo.getMillSetting() reads it back, writeThroughSync decides.
 *
 * Regression for audit 2026-10-04 (KRITIS): getMillSetting()'s SELECT
 * omitted `immediate_sync_enabled`, so isImmediateSyncEnabled() was always
 * false and "Kirim data langsung ke server saat disimpan" never activated —
 * invisible to the mocked-query specs, which return every column no matter
 * what the SQL says.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/services/localDb', async () => (await import('./support/sqlJsLocalDb')).localDbMock)
vi.mock('@/services/apiClient', () => ({ default: { get: vi.fn(), post: vi.fn() } }))

import apiClient from '@/services/apiClient'
import { resetSqlJsDb } from './support/sqlJsLocalDb'
import { initLocalSchema, fetchAndCacheMillSetting } from '@/services/localSchema'
import { getMillSetting } from '@/services/millSettingRepo'
import { isImmediateSyncEnabled } from '@/services/writeThroughSync'
import { useAuthStore } from '@/stores/auth'

const BU = 'bu-1'

function mockServer(immediate: boolean) {
  vi.mocked(apiClient.get).mockImplementation(async (url: string) => {
    if (url === '/api/mill-settings/current') {
      return {
        data: {
          business_unit_id: BU,
          app_name: 'Mill A',
          logo: null,
          home_page_image: null,
          jumlah_cages: 12,
          immediate_sync_enabled: immediate,
        },
      }
    }
    // station icon overrides etc.
    return { data: { data: [] } }
  })
}

beforeEach(async () => {
  vi.clearAllMocks()
  setActivePinia(createPinia())
  useAuthStore().user = { id: 'u-1', username: 'op', name: 'Op', role: 'operator', business_unit_id: BU }
  await resetSqlJsDb()
  await initLocalSchema()
})

describe('mill setting cache round-trip (real SQLite)', () => {
  it('immediate_sync_enabled = true on the server reaches isImmediateSyncEnabled()', async () => {
    mockServer(true)
    await fetchAndCacheMillSetting(BU)

    const setting = await getMillSetting(BU)
    expect(setting?.immediateSyncEnabled).toBe(true)
    expect(setting?.appName).toBe('Mill A')
    expect(await isImmediateSyncEnabled()).toBe(true)
  })

  it('turning it off on the server turns write-through off again after the next fetch', async () => {
    mockServer(true)
    await fetchAndCacheMillSetting(BU)
    mockServer(false)
    await fetchAndCacheMillSetting(BU)

    expect((await getMillSetting(BU))?.immediateSyncEnabled).toBe(false)
    expect(await isImmediateSyncEnabled()).toBe(false)
  })
})
