import { defineStore } from 'pinia'
import apiClient from '@/services/apiClient'
import { tokenStorage } from '@/services/tokenStorage'
import { fetchAndCacheMillSetting, seedDefaultStationsIfNeeded } from '@/services/localSchema'

/**
 * OFFLINE_GRACE_PERIOD_MS — screen-002--login-mobile / "Token Sesi Lokal
 * Kadaluarsa" placeholder. Token offline-validity duration is an explicit
 * open question in the business spec (no server-enforced expiry — see
 * config/sanctum.php's `expiration => null`), so this client-side heuristic
 * treats a locally-stored session as too stale to trust for *offline* use
 * once it has been offline-only for longer than this window. It never
 * blocks an *online* app open — restoreSession() only applies this check
 * while `navigator.onLine` is false. Revisit once product defines the real
 * offline-validity duration.
 */
const OFFLINE_GRACE_PERIOD_MS = 7 * 24 * 60 * 60 * 1000 // 7 days, placeholder

export interface AuthUser {
  id: string
  username: string
  name: string
  role: 'operator' | 'supervisor' | 'mill_management' | 'admin'
  business_unit_id: string | null
}

export interface BusinessUnitSummary {
  id: string
  name: string
}

export interface LoginCredentials {
  username: string
  password: string
}

interface AuthState {
  user: AuthUser | null
  token: string | null
  businessUnit: BusinessUnitSummary | null
  initialized: boolean
  /**
   * true when restoreSession() found a locally-stored session but rejected
   * it as stale-while-offline (see OFFLINE_GRACE_PERIOD_MS) — router/views
   * can use this to prompt "please reconnect and log in again" rather than
   * a generic "please log in".
   */
  sessionExpiredOffline: boolean
}

/**
 * auth store — client-side auth state (auth-store, shared-modules).
 *
 * session_strategy: the Sanctum token is persisted locally (see
 * services/tokenStorage.ts) and stays valid while the device is offline
 * after the first successful online login; it is only re-validated against
 * the server on the next online action (shared_decisions.auth.notes).
 * restoreSession() rehydrates this state from local storage on app start
 * without requiring network access.
 */
export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    token: null,
    businessUnit: null,
    initialized: false,
    sessionExpiredOffline: false,
  }),

  getters: {
    isAuthenticated: (state): boolean => Boolean(state.token && state.user),
    currentUser: (state): AuthUser | null => state.user,
    authToken: (state): string | null => state.token,
  },

  actions: {
    /**
     * screen-002--login-mobile / usecase-002--login-mobile. Always sends a
     * `device_name` — this is what selects the Sanctum token-issuing branch
     * of POST /api/login on the backend (App\Http\Controllers\Api\
     * AuthController::login()) instead of the screen-001 web/session
     * branch, and becomes the token's display name server-side.
     */
    async login(credentials: LoginCredentials): Promise<void> {
      const response = await apiClient.post('/api/login', {
        ...credentials,
        device_name: getDeviceName(),
      })
      const { token, user, business_unit: businessUnit } = response.data

      this.token = token
      this.user = user
      this.businessUnit = businessUnit ?? null
      this.initialized = true
      this.sessionExpiredOffline = false

      const issuedAt = Date.now()
      tokenStorage.setToken(token)
      tokenStorage.setUser(user)
      tokenStorage.setBusinessUnit(businessUnit ?? null)
      tokenStorage.setTokenIssuedAt(issuedAt)

      // screen-006--station-list fix (2026-08-18): the local `station`
      // table was never populated on a real device (no sync flow exists
      // anywhere in this project — see localSchema.ts's header comment),
      // leaving Station List permanently empty. The 15 MVP stations are
      // fixed domain data, so they're seeded locally right here, once
      // business_unit_id is known. Best-effort: a seed failure must not
      // block a successful login — Station List simply stays empty and
      // can be diagnosed separately, same principle as logout()'s
      // best-effort API call below.
      if (businessUnit?.id) {
        try {
          await seedDefaultStationsIfNeeded(businessUnit.id)
        } catch {
          // Local SQLite write failed — non-fatal, login already succeeded.
        }

        // Mills Setting feature (2026-08-19): mill-setting (app_name/logo/
        // home_page_image/jumlah_cages) is genuinely server-authored data
        // (edited via the web Mills Setting screen, screen-034), unlike the
        // fixed local station seed above — fetched here, best-effort, same
        // principle as seedDefaultStationsIfNeeded(): a failed/offline
        // fetch must not block a successful login. Screens consuming this
        // data (Home, Station List, Form Cages Track) each handle the
        // "not cached yet" case themselves (see their own tech specs).
        try {
          await fetchAndCacheMillSetting(businessUnit.id)
        } catch {
          // Offline or request failed — non-fatal, login already succeeded.
        }
      }
    },

    async logout(): Promise<void> {
      try {
        if (this.token) {
          await apiClient.post('/api/logout')
        }
      } catch {
        // Best-effort — always clear local state even if the device is
        // offline and can't reach the server to invalidate the token.
      } finally {
        this.user = null
        this.token = null
        this.businessUnit = null
        tokenStorage.clear()
      }
    },

    /**
     * Rehydrate auth state from local storage on app start. Works fully
     * offline — does not make a network call. If the token was
     * revoked/expired server-side, re-validation happens naturally on the
     * next authenticated API call (a 401 response should route the user
     * back to /login — see router's auth guard).
     *
     * "Token Sesi Lokal Kadaluarsa" (screen-002--login-mobile edge case):
     * while the device is offline, a locally-stored session older than
     * OFFLINE_GRACE_PERIOD_MS is treated as expired and NOT restored — the
     * user is left unauthenticated (router's auth guard then redirects to
     * /login) and `sessionExpiredOffline` is set so the login screen can
     * show a "please reconnect and log in again" message instead of a
     * generic one. While online, the stored session is always restored
     * as-is; staleness is instead caught by the next authenticated API
     * call returning 401.
     */
    restoreSession(): void {
      const token = tokenStorage.getToken()
      const issuedAt = tokenStorage.getTokenIssuedAt()
      const isOffline = typeof navigator !== 'undefined' && navigator.onLine === false

      if (token && isOffline && issuedAt !== null && Date.now() - issuedAt > OFFLINE_GRACE_PERIOD_MS) {
        this.user = null
        this.token = null
        this.businessUnit = null
        this.initialized = true
        this.sessionExpiredOffline = true
        tokenStorage.clear()

        return
      }

      this.token = token
      this.user = tokenStorage.getUser<AuthUser>()
      this.businessUnit = tokenStorage.getBusinessUnit<BusinessUnitSummary>()
      this.initialized = true
      this.sessionExpiredOffline = false
    },
  },
})

/**
 * Best-effort, stable-per-device label sent as `device_name` on login (used
 * server-side as the Sanctum token name). No native device-info plugin is
 * installed yet (see mobile/package.json — @capacitor/device would be the
 * proper source in a packaged build); this derives a short label from the
 * user agent and pins a random suffix in localStorage so the same device
 * reuses the same name across logins/token refreshes.
 */
function getDeviceName(): string {
  const STORAGE_KEY = 'msl_device_name'
  const cached = localStorage.getItem(STORAGE_KEY)

  if (cached) {
    return cached
  }

  const platform = typeof navigator !== 'undefined' ? navigator.userAgent : 'unknown-device'
  const label = /android/i.test(platform)
    ? 'Android Device'
    : /iphone|ipad|ios/i.test(platform)
      ? 'iOS Device'
      : 'Web Device'
  const suffix = Math.random().toString(36).slice(2, 8)
  const deviceName = `${label} - ${suffix}`

  localStorage.setItem(STORAGE_KEY, deviceName)

  return deviceName
}
