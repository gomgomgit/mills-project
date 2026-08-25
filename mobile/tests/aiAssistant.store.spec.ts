/**
 * aiAssistant.store.spec.ts — mobile/src/stores/aiAssistant.ts.
 *
 * `isOpen` — plain session-only state toggle, no localStorage. Locks in
 * open()/close() behavior so AiAssistantPanel.spec.ts and the various
 * *View.spec.ts nav-menu tests can rely on it.
 *
 * `bubbleEnabled` (2026-08-25) — mirrors floatingClock store's
 * enabled/toggle/localStorage shape, but defaults ON (not off) and uses
 * its own storage key (msl_ai_bubble_enabled) — see the store's own
 * header comment for why. Real jsdom localStorage (cleared in
 * beforeEach), not mocked — a real roundtrip is simpler and more
 * trustworthy than mocking get/set individually.
 */
import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAiAssistantStore } from '@/stores/aiAssistant'

describe('aiAssistant store', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('starts closed', () => {
    const store = useAiAssistantStore()
    expect(store.isOpen).toBe(false)
  })

  it('open() sets isOpen to true', () => {
    const store = useAiAssistantStore()
    store.open()
    expect(store.isOpen).toBe(true)
  })

  it('close() sets isOpen to false', () => {
    const store = useAiAssistantStore()
    store.open()
    store.close()
    expect(store.isOpen).toBe(false)
  })

  describe('bubbleEnabled', () => {
    it('defaults to true on a fresh install (no stored preference)', () => {
      const store = useAiAssistantStore()
      expect(store.bubbleEnabled).toBe(true)
    })

    it('toggleBubble() flips the value and persists it to localStorage', () => {
      const store = useAiAssistantStore()

      store.toggleBubble()
      expect(store.bubbleEnabled).toBe(false)
      expect(localStorage.getItem('msl_ai_bubble_enabled')).toBe('false')

      store.toggleBubble()
      expect(store.bubbleEnabled).toBe(true)
      expect(localStorage.getItem('msl_ai_bubble_enabled')).toBe('true')
    })

    it('reads a previously persisted "false" preference on init', () => {
      localStorage.setItem('msl_ai_bubble_enabled', 'false')

      const store = useAiAssistantStore()

      expect(store.bubbleEnabled).toBe(false)
    })
  })
})
