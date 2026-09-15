<script setup lang="ts">
/**
 * RecordVerificationStatus — read-only verification display for Data
 * Preview (2026-09-14).
 *
 * Replaces the old `<FormField :model-value="detailRecord.checked_by" …
 * disabled />`, which rendered the raw user UUID into a field labelled
 * "Checked By" — unreadable, and indistinguishable from "not verified" for
 * anyone who did not know what a blank vs. a uuid meant.
 *
 * Resolving a name is best-effort by design. There is no local `user`
 * table to join against offline, so the name comes from, in order:
 *   1. the `*_by_name` column, stored whenever THIS device performed or
 *      received the verification (see recordVerificationApi.ts),
 *   2. the auth store, when the id is the current user's own,
 *   3. nothing — a record verified by someone else on the web reaches this
 *      device with only an id, because nothing pulls server-side changes
 *      down. That case says "sudah diverifikasi" WITHOUT a name rather
 *      than showing a uuid or implying it was never verified.
 */
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{
  label: string
  verifierId?: string | null
  verifierName?: string | null
  /** Wording for the "not yet" state, e.g. 'Belum diperiksa Supervisor'. */
  pendingLabel: string
}>()

const authStore = useAuthStore()

const isVerified = computed(() => Boolean(props.verifierId))

const resolvedName = computed(() => {
  if (props.verifierName) return props.verifierName
  if (props.verifierId && props.verifierId === authStore.currentUser?.id) {
    return authStore.currentUser?.name ?? null
  }
  return null
})
</script>

<template>
  <div class="verify-status" :data-testid="`verify-status-${label.toLowerCase().replace(/\s+/g, '-')}`">
    <span class="verify-status__label">{{ label }}</span>

    <p v-if="!isVerified" class="verify-status__value verify-status__value--pending">
      {{ pendingLabel }}
    </p>
    <p v-else-if="resolvedName" class="verify-status__value verify-status__value--done">
      Oleh {{ resolvedName }}
    </p>
    <p v-else class="verify-status__value verify-status__value--done">
      Sudah diverifikasi
      <span class="verify-status__hint">(nama tidak tersedia offline)</span>
    </p>
  </div>
</template>

<style scoped>
.verify-status {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 12px;
}

.verify-status__label {
  font-size: 12px;
  color: #6b7280;
}

.verify-status__value {
  margin: 0;
  font-size: 14px;
}

.verify-status__value--pending {
  color: #d97706;
}

.verify-status__value--done {
  color: #249360;
  font-weight: 500;
}

.verify-status__hint {
  color: #6b7280;
  font-weight: 400;
  font-size: 12px;
}
</style>
