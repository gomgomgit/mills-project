<script setup lang="ts">
/**
 * SyncFailureHint — "Gagal sinkron: <alasan>" pada record yang masih
 * berstatus `saved` dan punya alasan penolakan tersimpan (kolom
 * `sync_error`, ditulis syncService saat server menolak, dikosongkan saat
 * berhasil). Audit 2026-10-04: sebelumnya record yang ditolak tetap tampak
 * "Tersimpan" di Data Preview tanpa petunjuk apa pun.
 *
 * Menerima record mentah apa pun (semua repo memakai `SELECT *`), jadi
 * tidak perlu menambah field ke 18 interface record.
 */
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    record: object | null | undefined
    compact?: boolean
  }>(),
  { compact: false },
)

const reason = computed(() => {
  const record = props.record as { status?: string; sync_error?: string | null } | null | undefined
  if (!record || record.status !== 'saved') return null
  const value = record.sync_error?.trim()
  return value ? value : null
})
</script>

<template>
  <span
    v-if="reason"
    class="sync-failure-hint"
    :class="{ 'sync-failure-hint--compact': compact }"
    role="note"
    data-testid="sync-failure-hint"
  >
    Gagal sinkron: {{ reason }}
  </span>
</template>

<style scoped>
.sync-failure-hint {
  display: block;
  margin: 0 0 12px;
  padding: 8px 10px;
  border-radius: 8px;
  background-color: #fef2f2;
  color: #b91c1c;
  font-size: 13px;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.sync-failure-hint--compact {
  margin: 4px 0 0;
  padding: 0;
  background-color: transparent;
  font-size: 12px;
}
</style>
