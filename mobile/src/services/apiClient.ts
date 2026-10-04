import axios, { type AxiosError, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/auth'
import { SESSION_REVOKED_MESSAGE } from '@/services/errorHandler'

/**
 * apiClient — shared Axios instance for all mobile -> backend API calls
 * (api-client, shared-modules).
 *
 * Base URL comes from VITE_API_BASE_URL (Vite env var, exposed at build
 * time). Every request attaches `Authorization: Bearer <token>` from the
 * auth store when a Sanctum token is present.
 *
 * Every response error is normalized into the shared error shape (see
 * shared_decisions.error_format: { message, errors? }) so fe-error-handler
 * can consume it uniformly regardless of the underlying failure (network,
 * validation, auth, server).
 */

export interface NormalizedApiError {
  message: string
  errors?: Record<string, string[]>
  status?: number
  /**
   * true ONLY when the request went out but no response came back at all
   * (offline / server unreachable / CORS-blocked). Lets callers tell a real
   * connectivity failure apart from any other error that also lacks a
   * `status` (a thrown Error, a request-setup failure).
   */
  network?: boolean
}

interface ApiErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

const apiClient: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  headers: {
    Accept: 'application/json',
  },
})

apiClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const authStore = useAuthStore()

  if (authStore.token) {
    config.headers = config.headers ?? {}
    config.headers.Authorization = `Bearer ${authStore.token}`
  }

  return config
})

/**
 * Penanganan 401 terpusat (audit 2026-10-05). Sebelumnya tidak ada: akun
 * yang dinonaktifkan Admin (token Sanctum-nya dicabut server, lihat
 * UserService::setStatus()) atau token yang dicabut tetap TAMPAK login di
 * aplikasi, dan sinkronisasi hanya gagal per record tanpa penjelasan.
 *
 * Sekarang setiap 401 dari request BERSESI:
 *   1. membersihkan sesi lokal (token/user/business unit) lewat
 *      authStore.expireSession() — TIDAK memanggil POST /api/logout (token
 *      sudah tidak berlaku) dan TIDAK menyentuh data lokal SQLite: draft &
 *      record yang belum tersinkron tetap ada dan muncul lagi setelah login
 *      ulang dengan akun yang sama;
 *   2. memanggil handler navigasi yang didaftarkan main.ts (ke Login), di
 *      mana LoginForm menampilkan SESSION_REVOKED_MESSAGE.
 *
 * Tidak dipicu untuk:
 *   - POST /api/login (401 = kredensial salah, ditangani LoginForm) dan
 *     POST /api/logout (logout() sudah membersihkan sesinya sendiri);
 *   - kegagalan jaringan / offline (tidak ada respons sama sekali);
 *   - request yang tidak membawa token sesi SAAT INI (mis. request lama
 *     yang tertunda dari sesi sebelum login ulang) — supaya 401 basi tidak
 *     membuang sesi baru yang sah.
 */
export { SESSION_REVOKED_MESSAGE }

const SESSION_EXEMPT_URLS = ['/api/login', '/api/logout']

type UnauthorizedHandler = () => void
let unauthorizedHandler: UnauthorizedHandler | null = null

/** Didaftarkan sekali oleh main.ts (navigasi ke Login); null untuk melepas. */
export function setUnauthorizedHandler(handler: UnauthorizedHandler | null): void {
  unauthorizedHandler = handler
}

function handleUnauthorized(error: AxiosError<ApiErrorBody>): void {
  if (error.response?.status !== 401) {
    return
  }

  const url = error.config?.url ?? ''
  if (SESSION_EXEMPT_URLS.some((exempt) => url === exempt || url.endsWith(exempt))) {
    return
  }

  const authStore = useAuthStore()
  const sentAuthorization = error.config?.headers?.Authorization
  if (!authStore.token || sentAuthorization !== `Bearer ${authStore.token}`) {
    // Bukan sesi aktif yang ditolak (sudah dibersihkan oleh 401 lain dalam
    // batch yang sama, atau request basi dari sesi sebelumnya).
    return
  }

  authStore.expireSession()
  unauthorizedHandler?.()
}

apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiErrorBody>) => {
    handleUnauthorized(error)

    return Promise.reject(normalizeError(error))
  },
)

function normalizeError(error: AxiosError<ApiErrorBody>): NormalizedApiError {
  if (error.response) {
    const data = error.response.data

    return {
      message: data?.message ?? 'Terjadi kesalahan. Silakan coba lagi.',
      errors: data?.errors,
      status: error.response.status,
    }
  }

  if (error.request) {
    // No response received — offline / network failure. The mobile app is
    // offline-first, so callers should catch this and fall back to local
    // SQLite persistence where applicable rather than surfacing a hard error.
    return {
      message: 'Tidak dapat terhubung ke server. Data akan disimpan secara lokal.',
      network: true,
    }
  }

  return {
    message: error.message || 'Terjadi kesalahan yang tidak diketahui.',
  }
}

export default apiClient
