import type { NormalizedApiError } from '@/services/apiClient'

/**
 * errorHandler — client-side API error display (fe-error-handler,
 * shared-modules).
 *
 * Maps a normalized API error (see apiClient.ts's response interceptor) to
 * a user-facing message, per shared_decisions.error_format:
 *   { message, errors? } — `errors` present only for 422 validation
 *   failures (joined into a single message here); every other error carries
 *   `message` only.
 *
 * showError() is the single entry point screens/components should call to
 * surface API failures to the user. It returns the display string; how it
 * is rendered (toast/snackbar/inline) is left to the UI layer calling this,
 * since no toast library is part of the current stack — see setup_notes.
 */

export type ShowErrorFn = (error: NormalizedApiError) => string

export const ERROR_MESSAGE_MAP: Record<number, string> = {
  401: 'Sesi Anda telah berakhir. Silakan login kembali.',
  403: 'Anda tidak memiliki akses untuk aksi ini.',
  404: 'Data tidak ditemukan.',
  422: 'Validasi gagal. Periksa kembali input Anda.',
  500: 'Terjadi kesalahan pada server. Silakan coba lagi.',
}

/**
 * Builds a single user-facing message string from a normalized API error.
 */
export function toDisplayMessage(error: NormalizedApiError): string {
  if (error.errors && Object.keys(error.errors).length > 0) {
    return Object.values(error.errors).flat().join(' ')
  }

  if (error.message) {
    return error.message
  }

  if (error.status && ERROR_MESSAGE_MAP[error.status]) {
    return ERROR_MESSAGE_MAP[error.status]
  }

  return 'Terjadi kesalahan yang tidak diketahui.'
}

/**
 * Default showError implementation — resolves the display message and logs
 * it for debugging. Returns the message so callers can feed it into
 * whatever UI affordance (toast, alert, inline field) is appropriate for
 * the screen calling it.
 */
export const showError: ShowErrorFn = (error) => {
  const message = toDisplayMessage(error)

  // eslint-disable-next-line no-console
  console.error('[API Error]', message, error)

  return message
}
