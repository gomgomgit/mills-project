<script setup lang="ts">
/**
 * StatusBadge — generic per-station status badge, first built for
 * screen-005--home / usecase-005--home but intentionally generic (props
 * only, no station-specific logic) so screens 006-015 can reuse it for the
 * same three-state model on their own screens.
 *
 * Per uiux-spec: hitam/netral = tidak ada input (no icon), merah (#D20000)
 * + ikon jam = ongoing, merah (#D20000) + ikon pause = paused — status is
 * distinguished by ICON *and* TEXT together, never by color alone
 * (accessibility: color-blind users / grayscale screenshots must still be
 * able to tell the states apart).
 *
 * Purely a display component (not interactive/tappable) — sizing still
 * follows the app's 44px touch-target convention (see
 * ChangePasswordForm.vue's `.submit-button`/`input` min-height) so it
 * stays visually consistent with tappable elements nearby (e.g. inside a
 * station row that IS tappable).
 */
import { computed } from 'vue'

export type BadgeStatus = 'none' | 'ongoing' | 'paused'

const props = withDefaults(
  defineProps<{
    status: BadgeStatus
    label?: string
  }>(),
  {
    label: undefined,
  },
)

const DEFAULT_LABELS: Record<BadgeStatus, string> = {
  none: 'Tidak ada input',
  ongoing: 'Sedang berlangsung',
  paused: 'Dijeda',
}

const displayLabel = computed(() => props.label ?? DEFAULT_LABELS[props.status])
</script>

<template>
  <span class="status-badge" :class="`status-badge--${status}`" role="status">
    <svg
      v-if="status === 'ongoing'"
      class="status-icon"
      viewBox="0 0 20 20"
      width="14"
      height="14"
      fill="none"
      aria-hidden="true"
    >
      <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.6" />
      <path d="M10 6v4l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <svg
      v-else-if="status === 'paused'"
      class="status-icon"
      viewBox="0 0 20 20"
      width="14"
      height="14"
      fill="none"
      aria-hidden="true"
    >
      <rect x="6" y="5" width="3" height="10" rx="1" fill="currentColor" />
      <rect x="11" y="5" width="3" height="10" rx="1" fill="currentColor" />
    </svg>

    <span class="status-label">{{ displayLabel }}</span>
  </span>
</template>

<style scoped>
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 28px;
  padding: 4px 10px;
  border-radius: 999px;
  font-family: 'Inter', sans-serif;
  font-size: 13px;
  font-weight: 500;
  line-height: 1;
  box-sizing: border-box;
}

.status-badge--none {
  color: #1f2937;
  background-color: #f3f4f6;
}

.status-badge--ongoing,
.status-badge--paused {
  color: #d20000;
  background-color: #fde8e8;
}

.status-icon {
  flex: none;
  color: inherit;
}

.status-label {
  white-space: nowrap;
}
</style>
