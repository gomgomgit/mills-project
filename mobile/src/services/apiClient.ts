import axios, { type AxiosError, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/auth'

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

apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiErrorBody>) => Promise.reject(normalizeError(error)),
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
    }
  }

  return {
    message: error.message || 'Terjadi kesalahan yang tidak diketahui.',
  }
}

export default apiClient
