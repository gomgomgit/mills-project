<script setup lang="ts">
/**
 * SyncResultDialog — info popup for the TEMPORARY "Sinkronisasi" button on
 * screen-006--station-list (see StationListView.vue's onSync() and
 * syncService.ts's doc comment for the full mechanism/scope). Previously
 * the sync result was an inline <p> under the button; moved to a popup so
 * a long result (many failed items, each with its own reason) doesn't
 * push the rest of the screen's layout around or get missed if it scrolls
 * off-screen.
 *
 * Mirrors ConfirmDialog.vue's overlay/dialog visual pattern (same
 * component family, this codebase's one reusable modal shell for mobile),
 * but is a single-button info dialog (no confirm/cancel choice) — closer
 * to an alert than a confirm.
 *
 * Visibility is controlled by the `open` prop (parent owns the boolean
 * state), same philosophy as ConfirmDialog.vue.
 */
import type { SyncSummary } from '@/services/syncService'

defineProps<{
  open: boolean
  summary: SyncSummary | null
  errorMessage: string | null
}>()

const emit = defineEmits<{
  close: []
}>()

function failedItems(summary: SyncSummary) {
  return [...summary.weighbridge, ...summary.grading, ...summary.cagesTrack].filter((item) => !item.ok)
}
</script>

<template>
  <div v-if="open" class="sync-dialog-overlay" @click.self="emit('close')">
    <div class="sync-dialog" role="alertdialog" aria-modal="true" aria-label="Hasil Sinkronisasi">
      <h2 class="sync-dialog-title">
        {{ errorMessage ? 'Sinkronisasi Gagal' : 'Sinkronisasi Selesai' }}
      </h2>

      <p v-if="errorMessage" class="sync-dialog-message sync-dialog-message--error" data-testid="sync-dialog-message">
        {{ errorMessage }}
      </p>

      <template v-else-if="summary">
        <p class="sync-dialog-message" data-testid="sync-dialog-message">
          {{ summary.syncedCount }} data berhasil disinkronkan{{ summary.failedCount > 0 ? `, ${summary.failedCount} gagal.` : '.' }}
        </p>

        <ul v-if="failedItems(summary).length > 0" class="sync-dialog-failed-list" data-testid="sync-dialog-failed-list">
          <li v-for="item in failedItems(summary)" :key="item.id" class="sync-dialog-failed-item">
            <span class="sync-dialog-failed-label">{{ item.label }}</span>
            <span class="sync-dialog-failed-reason">{{ item.reason }}</span>
          </li>
        </ul>
      </template>

      <button type="button" class="sync-dialog-button" data-testid="sync-dialog-close" @click="emit('close')">
        Tutup
      </button>
    </div>
  </div>
</template>

<style scoped>
.sync-dialog-overlay {
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

.sync-dialog {
  width: 100%;
  max-width: 360px;
  max-height: 80vh;
  padding: 20px;
  border-radius: 12px;
  background-color: #ffffff;
  font-family: 'Inter', sans-serif;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sync-dialog-title {
  margin: 0 0 8px;
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
}

.sync-dialog-message {
  margin: 0 0 12px;
  font-size: 14px;
  color: #6b7280;
}

.sync-dialog-message--error {
  color: #dc2626;
}

.sync-dialog-failed-list {
  list-style: none;
  margin: 0 0 16px;
  padding: 0;
  overflow-y: auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sync-dialog-failed-item {
  padding: 8px 10px;
  border-radius: 8px;
  background-color: #fef2f2;
}

.sync-dialog-failed-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #1f2937;
}

.sync-dialog-failed-reason {
  display: block;
  font-size: 13px;
  color: #dc2626;
}

.sync-dialog-button {
  min-height: 44px;
  border: none;
  border-radius: 8px;
  background-color: #249360;
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
}
</style>
