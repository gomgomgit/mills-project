<script setup lang="ts">
/**
 * Floating "Mills AI" quick-access bubble — bottom-right, stacked directly
 * above FloatingClock.vue (same corner, fixed offset so it never overlaps
 * the clock regardless of whether the clock is currently enabled — no
 * reactive repositioning, deliberately simple). Mounted once, globally,
 * in App.vue (same "mount once in the shared shell" principle as
 * FloatingClock.vue and AiAssistantPanel.vue).
 *
 * Added 2026-08-25 per explicit user request, revisiting the earlier
 * 2026-08-24 decision to NOT have a floating bubble on mobile (collision/
 * accidental-tap risk, see AiAssistantPanel.vue's original header
 * comment) — resolved by: (1) stacking above the clock instead of
 * overlapping it, (2) keeping it small (48px, smaller than the web
 * widget's 56px bubble — "jangan terlalu besar"), (3) making it
 * toggleable off via the hamburger nav-menu ("Aktifkan/Nonaktifkan Bubble
 * Chat AI"), same escape hatch as the clock, for anyone who still finds
 * it intrusive. Tapping it opens the same AiAssistantPanel.vue as the
 * nav-menu's "Bantuan AI" entry — this bubble is an ADDITIONAL quick
 * entry point, not a replacement.
 */
import { useAiAssistantStore } from '@/stores/aiAssistant'

const aiAssistantStore = useAiAssistantStore()
</script>

<template>
  <button
    v-if="aiAssistantStore.bubbleEnabled"
    type="button"
    class="ai-assistant-bubble"
    data-testid="ai-assistant-bubble"
    aria-label="Buka Mills AI"
    @click="aiAssistantStore.open()"
  >
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
    </svg>
  </button>
</template>

<style scoped>
.ai-assistant-bubble {
  position: fixed;
  bottom: 58px;
  right: 16px;
  z-index: 999;
  width: 48px;
  height: 48px;
  padding: 0;
  box-sizing: border-box;
  border: none;
  border-radius: 999px;
  background-color: #249360;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
}

.ai-assistant-bubble:active {
  background-color: #1d7a4e;
}
</style>
