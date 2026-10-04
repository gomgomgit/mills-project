/**
 * apiClient.unauthorized.spec.ts — audit 2026-10-05 #3: penanganan 401 global.
 *
 * Sebelumnya tidak ada penanganan 401 terpusat: akun yang dinonaktifkan
 * (token Sanctum-nya dicabut server) tetap tampak login di aplikasi, dan
 * sinkronisasi hanya gagal per record tanpa penjelasan. Sekarang interceptor
 * respons apiClient yang ASLI (bukan mock) menangani 401 dari request
 * bersesi: sesi lokal dibersihkan, Login menampilkan pesan, dan handler
 * navigasi dipanggil. Data lokal (SQLite) TIDAK disentuh sama sekali.
 *
 * Yang dimock hanya lapisan transport (adapter axios) dan localDb (untuk
 * membuktikan tidak ada satu pun query/DELETE ke database lokal).
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { AxiosError, type AxiosAdapter, type InternalAxiosRequestConfig } from 'axios'
import { createPinia, setActivePinia } from 'pinia'

const { localDbRun, localDbQuery } = vi.hoisted(() => ({ localDbRun: vi.fn(), localDbQuery: vi.fn() }))
vi.mock('@/services/localDb', () => ({ run: localDbRun, query: localDbQuery }))

import apiClient, { setUnauthorizedHandler, SESSION_REVOKED_MESSAGE } from '@/services/apiClient'
import { useAuthStore } from '@/stores/auth'
import { tokenStorage } from '@/services/tokenStorage'

const USER = { id: 'u-1', username: 'operator01', name: 'Operator', role: 'operator' as const, business_unit_id: 'bu-1' }

/** Adapter transport palsu: menjawab dengan status tertentu, atau tanpa respons (offline). */
function respondWith(status: number | 'offline'): AxiosAdapter {
  return async (config: InternalAxiosRequestConfig) => {
    if (status === 'offline') {
      throw new AxiosError('Network Error', 'ERR_NETWORK', config, {})
    }
    const response = { data: { message: 'Unauthenticated.' }, status, statusText: '', headers: {}, config }
    if (status >= 400) {
      throw new AxiosError('Request failed', 'ERR_BAD_REQUEST', config, {}, response)
    }
    return response
  }
}

function loginAs(): ReturnType<typeof useAuthStore> {
  const store = useAuthStore()
  store.token = 'tok-1'
  store.user = USER
  store.businessUnit = { id: 'bu-1', name: 'BU A' }
  store.initialized = true
  tokenStorage.setToken('tok-1')
  tokenStorage.setUser(USER)
  tokenStorage.setBusinessUnit({ id: 'bu-1', name: 'BU A' })
  tokenStorage.setTokenIssuedAt(Date.now())
  return store
}

const originalAdapter = apiClient.defaults.adapter
let handler: ReturnType<typeof vi.fn>

beforeEach(() => {
  localStorage.clear()
  localStorage.setItem('msl_device_name', 'Web Device - abc123')
  setActivePinia(createPinia())
  handler = vi.fn()
  setUnauthorizedHandler(handler)
  vi.clearAllMocks()
})

afterEach(() => {
  apiClient.defaults.adapter = originalAdapter
  setUnauthorizedHandler(null)
})

describe('apiClient — 401 dari request bersesi (token dicabut / akun dinonaktifkan)', () => {
  it('membersihkan sesi lokal, menandai sessionRevoked, memanggil handler sekali, tetap menolak dengan status 401', async () => {
    const store = loginAs()
    apiClient.defaults.adapter = respondWith(401)

    await expect(apiClient.post('/api/threshing-records', {})).rejects.toMatchObject({ status: 401 })

    expect(store.token).toBeNull()
    expect(store.user).toBeNull()
    expect(store.isAuthenticated).toBe(false)
    expect(store.sessionRevoked).toBe(true)
    expect(tokenStorage.getToken()).toBeNull()
    expect(tokenStorage.getUser()).toBeNull()
    expect(handler).toHaveBeenCalledTimes(1)
    expect(SESSION_REVOKED_MESSAGE).toBe('Sesi berakhir atau akun dinonaktifkan. Silakan login kembali.')
  })

  it('TIDAK menyentuh data lokal: tidak ada query/DELETE ke SQLite, key localStorage lain tetap ada', async () => {
    loginAs()
    localStorage.setItem('msl_active_station', 'st-1')
    apiClient.defaults.adapter = respondWith(401)

    await expect(apiClient.get('/api/me')).rejects.toBeTruthy()

    expect(localDbRun).not.toHaveBeenCalled()
    expect(localDbQuery).not.toHaveBeenCalled()
    expect(localStorage.getItem('msl_device_name')).toBe('Web Device - abc123')
    expect(localStorage.getItem('msl_active_station')).toBe('st-1')
  })

  it('beberapa 401 serentak (mis. satu batch sinkron) hanya memicu handler sekali', async () => {
    loginAs()
    apiClient.defaults.adapter = respondWith(401)

    await Promise.allSettled([apiClient.post('/api/a'), apiClient.post('/api/b'), apiClient.post('/api/c')])

    expect(handler).toHaveBeenCalledTimes(1)
  })
})

describe('apiClient — kasus yang TIDAK boleh memicu penanganan sesi', () => {
  it('401 dari POST /api/login (kredensial salah) tidak membersihkan apa pun', async () => {
    const store = useAuthStore()
    apiClient.defaults.adapter = respondWith(401)

    await expect(apiClient.post('/api/login', {})).rejects.toMatchObject({ status: 401 })

    expect(store.sessionRevoked).toBe(false)
    expect(handler).not.toHaveBeenCalled()
  })

  it('offline (tanpa respons) tidak membersihkan sesi', async () => {
    const store = loginAs()
    apiClient.defaults.adapter = respondWith('offline')

    await expect(apiClient.post('/api/threshing-records', {})).rejects.toMatchObject({ network: true })

    expect(store.token).toBe('tok-1')
    expect(tokenStorage.getToken()).toBe('tok-1')
    expect(handler).not.toHaveBeenCalled()
  })

  it('status error lain (403/422/500) tidak membersihkan sesi', async () => {
    const store = loginAs()
    for (const status of [403, 422, 500]) {
      apiClient.defaults.adapter = respondWith(status)
      await expect(apiClient.get('/api/x')).rejects.toMatchObject({ status })
    }

    expect(store.token).toBe('tok-1')
    expect(handler).not.toHaveBeenCalled()
  })

  it('401 untuk request lama yang membawa token LAIN (sesi sudah diganti login baru) tidak membuang sesi baru', async () => {
    const store = loginAs()
    apiClient.defaults.adapter = async (config) => {
      // Selama request tertunda, pengguna sudah login ulang dengan token baru.
      store.token = 'tok-2'
      return respondWith(401)(config)
    }

    await expect(apiClient.get('/api/x')).rejects.toMatchObject({ status: 401 })

    expect(store.token).toBe('tok-2')
    expect(handler).not.toHaveBeenCalled()
  })

  it('login berhasil sesudahnya menghapus tanda sessionRevoked', async () => {
    const store = loginAs()
    apiClient.defaults.adapter = respondWith(401)
    await expect(apiClient.get('/api/x')).rejects.toBeTruthy()
    expect(store.sessionRevoked).toBe(true)

    apiClient.defaults.adapter = async (config) => ({
      data: config.url === '/api/login' ? { token: 'tok-3', user: USER, business_unit: null } : [],
      status: 200,
      statusText: '',
      headers: {},
      config,
    })
    await store.login({ username: 'operator01', password: 'Passw0rd!' })

    expect(store.sessionRevoked).toBe(false)
    expect(store.token).toBe('tok-3')
  })
})
