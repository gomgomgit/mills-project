<script setup lang="ts">
/**
 * ConfirmDialog — generic reusable confirm/cancel modal, first built for
 * screen-007--monitor-weighbridge's 'Clear' action (business_logic step
 * 5: "show confirm dialog → if confirmed: DELETE; if cancelled: no
 * change"), but deliberately generic/decoupled from weighbridge specifics
 * (title/message/labels are all props) so screens 008/009 (grading,
 * cages-track) can reuse it for the same destructive-confirm pattern
 * instead of each screen re-implementing its own dialog.
 *
 * Visibility is controlled by the `open` prop (parent owns the boolean
 * state) rather than this component managing its own open/closed state —
 * keeps it a pure, easily component-testable presentational element, same
 * philosophy as PausedDraftsList.vue (router-agnostic, emits events, lets
 * the parent act).
 */
withDefaults(
  defineProps<{
    open: boolean
    title?: string
    message: string
    confirmLabel?: string
    cancelLabel?: string
  }>(),
  {
    title: 'Konfirmasi',
    confirmLabel: 'Ya, Hapus',
    cancelLabel: 'Batal',
  },
)

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()
</script>

<template>
  <div v-if="open" class="confirm-dialog-overlay" @click.self="emit('cancel')">
    <div class="confirm-dialog" role="alertdialog" aria-modal="true" :aria-label="title">
      <h2 class="confirm-dialog-title">{{ title }}</h2>
      <p class="confirm-dialog-message">{{ message }}</p>

      <div class="confirm-dialog-actions">
        <button type="button" class="confirm-dialog-button confirm-dialog-button--cancel" @click="emit('cancel')">
          {{ cancelLabel }}
        </button>
        <button type="button" class="confirm-dialog-button confirm-dialog-button--confirm" @click="emit('confirm')">
          {{ confirmLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.confirm-dialog-overlay {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background-color: rgba(17, 24, 39, 0.5);
  z-index: 1000;
  box-sizing: border-box;
}

.confirm-dialog {
  width: 100%;
  max-width: 320px;
  padding: 20px;
  border-radius: 12px;
  background-color: #ffffff;
  font-family: 'Inter', sans-serif;
  box-sizing: border-box;
}

.confirm-dialog-title {
  margin: 0 0 8px;
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
}

.confirm-dialog-message {
  margin: 0 0 20px;
  font-size: 14px;
  color: #6b7280;
}

.confirm-dialog-actions {
  display: flex;
  gap: 10px;
}

.confirm-dialog-button {
  flex: 1;
  min-height: 44px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  box-sizing: border-box;
}

.confirm-dialog-button--cancel {
  border: 1px solid #e5e7eb;
  background-color: #ffffff;
  color: #1f2937;
}

.confirm-dialog-button--confirm {
  border: none;
  background-color: #d20000;
  color: #ffffff;
}
</style>
