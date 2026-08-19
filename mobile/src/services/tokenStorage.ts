/**
 * tokenStorage — persistence for the Sanctum auth token (+ minimal user /
 * business-unit info) across app restarts, supporting offline restore per
 * shared_decisions.auth: the mobile token does not auto-expire while the
 * device is offline; it is only re-validated on the next online action.
 *
 * TODO: swap the localStorage implementation below for
 * `@capacitor/preferences` (secure, native key-value storage) once that
 * dependency is added to mobile/package.json — see setup_notes. localStorage
 * works for web/dev builds today but is not the durable on-device store for
 * a packaged native build.
 */

const TOKEN_KEY = 'msl_auth_token'
const USER_KEY = 'msl_auth_user'
const BUSINESS_UNIT_KEY = 'msl_auth_business_unit'
const TOKEN_ISSUED_AT_KEY = 'msl_auth_token_issued_at'

function readJson<T>(key: string): T | null {
  const raw = localStorage.getItem(key)

  if (!raw) {
    return null
  }

  try {
    return JSON.parse(raw) as T
  } catch {
    return null
  }
}

export const tokenStorage = {
  getToken(): string | null {
    return localStorage.getItem(TOKEN_KEY)
  },

  setToken(token: string): void {
    localStorage.setItem(TOKEN_KEY, token)
  },

  hasToken(): boolean {
    return Boolean(localStorage.getItem(TOKEN_KEY))
  },

  /**
   * screen-002--login-mobile / "Token Sesi Lokal Kadaluarsa" support:
   * timestamp (ms epoch) of the last successful login, used by
   * stores/auth.ts's restoreSession() to apply an offline grace-period
   * heuristic. Token offline-validity duration is an explicit open
   * question (see implementation_notes) — this is a placeholder pending a
   * product decision, not a server-enforced expiry.
   */
  getTokenIssuedAt(): number | null {
    const raw = localStorage.getItem(TOKEN_ISSUED_AT_KEY)

    return raw ? Number(raw) : null
  },

  setTokenIssuedAt(timestampMs: number): void {
    localStorage.setItem(TOKEN_ISSUED_AT_KEY, String(timestampMs))
  },

  getUser<T>(): T | null {
    return readJson<T>(USER_KEY)
  },

  setUser(user: unknown): void {
    localStorage.setItem(USER_KEY, JSON.stringify(user))
  },

  getBusinessUnit<T>(): T | null {
    return readJson<T>(BUSINESS_UNIT_KEY)
  },

  setBusinessUnit(businessUnit: unknown): void {
    localStorage.setItem(BUSINESS_UNIT_KEY, JSON.stringify(businessUnit))
  },

  clear(): void {
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(USER_KEY)
    localStorage.removeItem(BUSINESS_UNIT_KEY)
    localStorage.removeItem(TOKEN_ISSUED_AT_KEY)
  },
}
