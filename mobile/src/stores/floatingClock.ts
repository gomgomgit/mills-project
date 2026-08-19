import { defineStore } from 'pinia'

const STORAGE_KEY = 'msl_floating_clock_enabled'

function readInitialEnabled(): boolean {
  try {
    return window.localStorage.getItem(STORAGE_KEY) === 'true'
  } catch {
    // localStorage unavailable (e.g. private mode) — default off, no floating clock.
    return false
  }
}

interface FloatingClockState {
  enabled: boolean
}

/**
 * floating-clock store — toggle state for the always-on-top clock overlay,
 * accessible from every screen's hamburger nav-menu. Persisted to
 * localStorage (not a per-user/server setting) so the preference survives
 * app reloads without needing a network round-trip.
 */
export const useFloatingClockStore = defineStore('floatingClock', {
  state: (): FloatingClockState => ({
    enabled: readInitialEnabled(),
  }),

  actions: {
    toggle(): void {
      this.enabled = !this.enabled
      try {
        window.localStorage.setItem(STORAGE_KEY, String(this.enabled))
      } catch {
        // localStorage unavailable — toggle still works for this session, just doesn't persist.
      }
    },
  },
})
