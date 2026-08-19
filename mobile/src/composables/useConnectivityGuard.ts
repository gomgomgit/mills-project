import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { tokenStorage } from '@/services/tokenStorage'

/**
 * useConnectivityGuard — client-side-only connectivity checks shared by
 * every screen whose business_logic requires blocking an action while the
 * device is offline, with no API call attempted.
 *
 * Originated for screen-002--login-mobile / usecase-002--login-mobile,
 * edge case "Tidak Ada Koneksi Saat Login Pertama" (no connection on
 * first-ever login) — `blocksFirstLogin` / `blockMessage` below implement
 * that exact rule and are UNCHANGED (still the login screen's only
 * concern: submit is blocked when BOTH offline AND the device has never
 * had a token stored; a device that has logged in before is allowed to
 * attempt submit while offline too, since the API call itself will
 * fail-fast if the network truly isn't reachable).
 *
 * Generalized for screen-004--ganti-password-mobile /
 * usecase-004--ganti-password-mobile, edge case "Tidak ada koneksi
 * internet" — unlike login, this action has no "device has logged in
 * before" exception: it must be blocked unconditionally whenever offline.
 * `blocksAction` / `offlineActionMessage` below cover that (and any other
 * future screen with the same simple "must be online" requirement) without
 * touching the existing `blocksFirstLogin` behavior or its call sites.
 *
 * Uses `navigator.onLine` rather than the Capacitor Network plugin: no
 * `@capacitor/network` dependency is installed yet (see mobile/package.json)
 * and `navigator.onLine` is available in both the Vite web dev build and a
 * packaged Capacitor WebView, so it is a safe default without adding a new
 * dependency — see known_issues for the follow-up to swap in the native
 * plugin for more accurate connectivity detection.
 */
export interface ConnectivityGuardOptions {
  /**
   * Message returned as `offlineActionMessage` when `blocksAction` is true.
   * Defaults to a generic "connection required" message; pass a
   * screen-specific message at the call site (e.g.
   * screen-004--ganti-password-mobile's "Tidak ada koneksi internet"
   * wording) to override it. Does not affect `blockMessage` /
   * `blocksFirstLogin`, which remain screen-002-specific and unparameterized.
   */
  offlineActionMessage?: string
}

export function useConnectivityGuard(options: ConnectivityGuardOptions = {}) {
  const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true)

  function updateOnlineStatus() {
    isOnline.value = navigator.onLine
  }

  onMounted(() => {
    window.addEventListener('online', updateOnlineStatus)
    window.addEventListener('offline', updateOnlineStatus)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('online', updateOnlineStatus)
    window.removeEventListener('offline', updateOnlineStatus)
  })

  const hasStoredToken = computed(() => tokenStorage.hasToken())

  /**
   * true when submit must be blocked client-side (no API call made).
   */
  const blocksFirstLogin = computed(() => !isOnline.value && !hasStoredToken.value)

  const blockMessage = 'Koneksi internet diperlukan untuk login pertama kali'

  /**
   * Generic "must be online" guard — true whenever offline, with no
   * exception (unlike `blocksFirstLogin`). Used by screen-004--ganti
   * -password-mobile and any future screen whose action cannot be queued
   * for later/offline retry.
   */
  const blocksAction = computed(() => !isOnline.value)

  const offlineActionMessage = options.offlineActionMessage ?? 'Koneksi internet diperlukan untuk melakukan aksi ini.'

  return {
    isOnline,
    hasStoredToken,
    blocksFirstLogin,
    blockMessage,
    blocksAction,
    offlineActionMessage,
  }
}
