/**
 * auth.store.spec.ts — screen-002--login-mobile / usecase-002--login-mobile.
 *
 * Unit tests for stores/auth.ts's restoreSession(), covering the
 * "Token Sesi Lokal Kadaluarsa" (locally stored session expired) test
 * scenario. This scenario is about app-boot session rehydration while
 * offline — not the LoginForm submit flow — so it targets the Pinia store
 * directly rather than the component (see LoginForm.spec.ts for the other 6
 * scenarios, which do target LoginForm).
 *
 * Per stores/auth.ts's documented behavior: while navigator.onLine is
 * false, a locally-stored session whose issuedAt timestamp is older than
 * OFFLINE_GRACE_PERIOD_MS (7 days, placeholder heuristic) is treated as
 * expired — cleared, and `sessionExpiredOffline` is set to true — and NOT
 * restored. While online, staleness is never checked client-side here; the
 * stored session is always restored as-is, and re-validation is deferred to
 * the next authenticated API call (401 handling), per the store's comments.
 *
 * '@/services/apiClient' is mocked at module level purely so importing
 * stores/auth.ts (which imports it) does not pull in real Axios / trigger
 * the apiClient <-> auth store circular import at real-module resolution —
 * it is not exercised by these tests (restoreSession() makes no network
 * calls).
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { tokenStorage } from '@/services/tokenStorage'

const OFFLINE_GRACE_PERIOD_MS = 7 * 24 * 60 * 60 * 1000 // mirrors stores/auth.ts's placeholder constant

vi.mock('@/services/apiClient', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

vi.mock('@/services/tokenStorage', () => ({
  tokenStorage: {
    getToken: vi.fn(),
    setToken: vi.fn(),
    hasToken: vi.fn(),
    getTokenIssuedAt: vi.fn(),
    setTokenIssuedAt: vi.fn(),
    getUser: vi.fn(),
    setUser: vi.fn(),
    getBusinessUnit: vi.fn(),
    setBusinessUnit: vi.fn(),
    clear: vi.fn(),
  },
}))

const STORED_USER = { id: 'user-1', username: 'operator01', name: 'Operator Satu', role: 'operator' }
const STORED_BUSINESS_UNIT = { id: 'bu-001', name: 'Mill A' }

describe('auth store — restoreSession() ("Token Sesi Lokal Kadaluarsa")', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.mocked(tokenStorage.getUser).mockReturnValue(STORED_USER)
    vi.mocked(tokenStorage.getBusinessUnit).mockReturnValue(STORED_BUSINESS_UNIT)
  })

  afterEach(() => {
    Object.defineProperty(navigator, 'onLine', { value: true, configurable: true })
  })

  it('scenario: token sesi lokal kadaluarsa — clears a stale session and flags sessionExpiredOffline while offline', () => {
    Object.defineProperty(navigator, 'onLine', { value: false, configurable: true })
    vi.mocked(tokenStorage.getToken).mockReturnValue('stale-token')
    vi.mocked(tokenStorage.getTokenIssuedAt).mockReturnValue(Date.now() - (OFFLINE_GRACE_PERIOD_MS + 60_000))

    const store = useAuthStore()
    store.restoreSession()

    expect(store.token).toBeNull()
    expect(store.user).toBeNull()
    expect(store.businessUnit).toBeNull()
    expect(store.initialized).toBe(true)
    expect(store.sessionExpiredOffline).toBe(true)
    expect(store.isAuthenticated).toBe(false)
    expect(tokenStorage.clear).toHaveBeenCalledTimes(1)
  });

  it('does NOT treat a stale-by-timestamp session as expired while online — defers to the server instead', () => {
    Object.defineProperty(navigator, 'onLine', { value: true, configurable: true })
    vi.mocked(tokenStorage.getToken).mockReturnValue('stale-token')
    vi.mocked(tokenStorage.getTokenIssuedAt).mockReturnValue(Date.now() - (OFFLINE_GRACE_PERIOD_MS + 60_000))

    const store = useAuthStore()
    store.restoreSession()

    expect(store.token).toBe('stale-token')
    expect(store.user).toEqual(STORED_USER)
    expect(store.businessUnit).toEqual(STORED_BUSINESS_UNIT)
    expect(store.sessionExpiredOffline).toBe(false)
    expect(store.isAuthenticated).toBe(true)
    expect(tokenStorage.clear).not.toHaveBeenCalled()
  });

  it('restores a fresh (within grace period) session normally while offline', () => {
    Object.defineProperty(navigator, 'onLine', { value: false, configurable: true })
    vi.mocked(tokenStorage.getToken).mockReturnValue('fresh-token')
    vi.mocked(tokenStorage.getTokenIssuedAt).mockReturnValue(Date.now() - 60_000)

    const store = useAuthStore()
    store.restoreSession()

    expect(store.token).toBe('fresh-token')
    expect(store.sessionExpiredOffline).toBe(false)
    expect(store.isAuthenticated).toBe(true)
    expect(tokenStorage.clear).not.toHaveBeenCalled()
  });

  it('does not error when there is no locally-stored session at all', () => {
    Object.defineProperty(navigator, 'onLine', { value: false, configurable: true })
    vi.mocked(tokenStorage.getToken).mockReturnValue(null)
    vi.mocked(tokenStorage.getTokenIssuedAt).mockReturnValue(null)
    vi.mocked(tokenStorage.getUser).mockReturnValue(null)
    vi.mocked(tokenStorage.getBusinessUnit).mockReturnValue(null)

    const store = useAuthStore()
    store.restoreSession()

    expect(store.token).toBeNull()
    expect(store.isAuthenticated).toBe(false);
    expect(store.sessionExpiredOffline).toBe(false)
  });
})

// login() — master Quality Parameter Grading ikut diambil saat login
// (2026-10-04). Tanpa ini Form Grading di perangkat baru menampilkan parameter
// bawaan ber-id palsu sampai sinkron pertama. Best-effort: gagal tidak
// menggagalkan login.
describe('auth store — login() memuat master Grading', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('memanggil fetchAndCacheGradingParameters setelah login berhasil, dan login tetap berhasil bila gagal', async () => {
    const gradingSync = await import('@/services/gradingParameterSync')
    const localSchema = await import('@/services/localSchema')
    const fetchGrading = vi.spyOn(gradingSync, 'fetchAndCacheGradingParameters').mockRejectedValue(new Error('offline'))
    vi.spyOn(localSchema, 'fetchAndCacheMillSetting').mockResolvedValue(undefined as never)

    const apiClient = (await import('@/services/apiClient')).default
    vi.mocked(apiClient.post).mockResolvedValue({
      data: { token: 't-1', user: STORED_USER, business_unit: STORED_BUSINESS_UNIT },
    } as never)

    const store = useAuthStore()
    await store.login({ username: 'operator01', password: 'Passw0rd!' } as never)

    expect(fetchGrading).toHaveBeenCalledTimes(1)
    expect(store.isAuthenticated).toBe(true)
  })
})

describe('auth store — logout() loading state (audit loading state 2026-10-05)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loggingOut true selama POST /api/logout; ketukan kedua tidak mengirim POST kedua', async () => {
    const apiClient = (await import('@/services/apiClient')).default
    let release!: () => void
    vi.mocked(apiClient.post).mockReturnValue(
      new Promise((resolve) => {
        release = () => resolve({ data: {} })
      }) as never,
    )

    const store = useAuthStore()
    store.$patch({ token: 't-1', user: STORED_USER as never })

    const first = store.logout()
    const second = store.logout()

    expect(store.loggingOut).toBe(true)
    expect(apiClient.post).toHaveBeenCalledTimes(1)

    release()
    await Promise.all([first, second])

    expect(store.loggingOut).toBe(false)
    expect(store.isAuthenticated).toBe(false)
  })

  it('loggingOut kembali false walau POST gagal (offline)', async () => {
    const apiClient = (await import('@/services/apiClient')).default
    vi.mocked(apiClient.post).mockRejectedValue(new Error('offline'))

    const store = useAuthStore()
    store.$patch({ token: 't-1', user: STORED_USER as never })

    await store.logout()

    expect(store.loggingOut).toBe(false)
    expect(store.token).toBeNull()
  })
})
