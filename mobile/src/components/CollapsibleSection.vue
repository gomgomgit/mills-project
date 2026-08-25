<script setup lang="ts">
/**
 * CollapsibleSection — generic collapse/expand wrapper, first built for
 * the 4 station Form views' "Target Operasional" reference table
 * (2026-08-25, per user request: it's read-only/reference-only content
 * that pushed the Simpan/Pause/Clear footer far down the page, forcing a
 * lot of scrolling on every save — collapsed by default removes that
 * scroll cost, at the trade-off of an extra tap for anyone who does want
 * to check it). Deliberately generic (title is a prop, content is the
 * default slot) so any other long reference-only section can reuse it
 * later, same philosophy as ConfirmDialog.vue.
 *
 * `v-show` (not `v-if`) for the body — matches AiAssistantPanel.vue's own
 * reasoning: content that legitimately exists whether or not it's
 * currently shown, so tests can assert on it (`findAll('tbody tr')`,
 * `.text()`) regardless of expanded state, and toggling doesn't pay a
 * re-mount cost.
 */
import { ref } from 'vue'

defineProps<{
  title: string
}>()

const expanded = ref(false)
</script>

<template>
  <section class="collapsible-section" :aria-label="title">
    <button
      type="button"
      class="collapsible-section__header"
      :aria-expanded="expanded"
      data-testid="collapsible-section-toggle"
      @click="expanded = !expanded"
    >
      <span class="collapsible-section__title">{{ title }}</span>
      <svg
        class="collapsible-section__chevron"
        :class="{ 'collapsible-section__chevron--expanded': expanded }"
        viewBox="0 0 24 24"
        width="18"
        height="18"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <polyline points="6 9 12 15 18 9" />
      </svg>
    </button>

    <div v-show="expanded" class="collapsible-section__body" data-testid="collapsible-section-body">
      <slot />
    </div>
  </section>
</template>

<style scoped>
.collapsible-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 16px;
}

.collapsible-section__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 0 0 6px;
  border: none;
  border-bottom: 1px solid #e5e7eb;
  background: transparent;
  cursor: pointer;
  min-height: 44px;
  font-family: inherit;
  text-align: left;
}

.collapsible-section__title {
  font-size: 13px;
  font-weight: 700;
  color: #249360;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.collapsible-section__chevron {
  flex-shrink: 0;
  color: #249360;
  transition: transform 0.15s ease;
}

.collapsible-section__chevron--expanded {
  transform: rotate(180deg);
}

.collapsible-section__body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
</style>
