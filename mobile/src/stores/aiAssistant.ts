import { defineStore } from 'pinia'

const BUBBLE_STORAGE_KEY = 'msl_ai_bubble_enabled'

function readInitialBubbleEnabled(): boolean {
  try {
    const stored = window.localStorage.getItem(BUBBLE_STORAGE_KEY)
    // Unlike floatingClock (default off), the bubble defaults ON — it's
    // the primary quick-access entry point for the AI assistant, so a
    // first-time user should see it without having to discover the
    // hamburger toggle first. `null` means "never explicitly set" (a
    // fresh install), not "explicitly disabled".
    return stored === null ? true : stored === 'true'
  } catch {
    return true
  }
}

interface AiAssistantState {
  isOpen: boolean
  bubbleEnabled: boolean
}

/**
 * ai-assistant store — open/closed state for the "Bantuan AI" panel,
 * triggered from every screen's hamburger nav-menu ("Bantuan AI") or the
 * floating AiAssistantBubble.vue. Mounted once, globally, in App.vue
 * (same pattern as the floatingClock store) so any screen can open it
 * without a per-view panel instance.
 *
 * `isOpen` is session-only (not persisted) — unlike floatingClock's
 * preference, staying open across app reloads isn't a meaningful default.
 *
 * `bubbleEnabled` (2026-08-25) IS persisted to localStorage, same
 * mechanics as floatingClock's `enabled` — toggled from the hamburger
 * nav-menu ("Aktifkan/Nonaktifkan Bubble Chat AI"), controls whether
 * AiAssistantBubble.vue renders at all. The bubble sits directly above
 * FloatingClock.vue (same bottom-right corner, stacked, not overlapping)
 * per user request 2026-08-25 — kept toggleable off for users who find a
 * persistent floating button intrusive, same rationale as the clock.
 */
export const useAiAssistantStore = defineStore('aiAssistant', {
  state: (): AiAssistantState => ({
    isOpen: false,
    bubbleEnabled: readInitialBubbleEnabled(),
  }),

  actions: {
    open(): void {
      this.isOpen = true
    },
    close(): void {
      this.isOpen = false
    },
    toggleBubble(): void {
      this.bubbleEnabled = !this.bubbleEnabled
      try {
        window.localStorage.setItem(BUBBLE_STORAGE_KEY, String(this.bubbleEnabled))
      } catch {
        // localStorage unavailable — toggle still works for this session, just doesn't persist.
      }
    },
  },
})
