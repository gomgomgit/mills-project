<script setup lang="ts">
/**
 * RecordVerificationActions — approve/un-approve buttons shown on every
 * station's Data Preview screen in detail mode (2026-09-14 product
 * decision; the web Detail screens got the same action via
 * resources/views/components/record-verification-actions.blade.php).
 *
 * Role rule matches the backend exactly (RecordVerificationService):
 * Supervisor writes Checked By, Mill Management writes Acknowledged By,
 * Admin writes both, Operator writes neither. Grading never collects
 * Checked By, so `supportsChecked` is passed false by that one view.
 *
 * Online-only — see recordVerificationApi.ts's header for why this app's
 * one-way sync design has nowhere to queue an offline verification. The
 * failure is surfaced in-place instead of being swallowed.
 */
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import {
  isNetworkError,
  serverIdOf,
  setVerification,
  type VerifiableRecord,
  type VerificationLevel,
} from '@/services/recordVerificationApi'

const props = withDefaults(
  defineProps<{
    stationType: string
    localTable: string
    record: VerifiableRecord | null
    supportsChecked?: boolean
  }>(),
  { supportsChecked: true },
)

const emit = defineEmits<{ (e: 'updated'): void }>()

const authStore = useAuthStore()
const busy = ref<VerificationLevel | null>(null)
const message = ref<string | null>(null)
const messageIsError = ref(false)

const role = computed(() => authStore.currentUser?.role ?? null)

const canCheck = computed(
  () => props.supportsChecked && (role.value === 'supervisor' || role.value === 'admin'),
)
const canAcknowledge = computed(() => role.value === 'mill_management' || role.value === 'admin')
const visible = computed(() => Boolean(props.record) && (canCheck.value || canAcknowledge.value))

const isChecked = computed(() => Boolean(props.record?.checked_by))
const isAcknowledged = computed(() => Boolean(props.record?.acknowledged_by))

/** Never synced → no server row to verify against. */
const notSynced = computed(() => Boolean(props.record) && serverIdOf(props.record) === null)

async function toggle(level: VerificationLevel): Promise<void> {
  if (!props.record || busy.value) return

  const turningOn = level === 'checked' ? !isChecked.value : !isAcknowledged.value

  busy.value = level
  message.value = null
  messageIsError.value = false

  try {
    await setVerification(props.stationType, props.localTable, props.record, level, turningOn)
    message.value = turningOn ? 'Verifikasi tersimpan.' : 'Verifikasi dibatalkan.'
    emit('updated')
  } catch (error) {
    messageIsError.value = true
    message.value = isNetworkError(error)
      ? 'Butuh koneksi internet untuk verifikasi. Coba lagi saat online.'
      : error instanceof Error
        ? error.message
        : ((error as { message?: string }).message ?? 'Gagal menyimpan verifikasi.')
  } finally {
    busy.value = null
  }
}
</script>

<template>
  <section v-if="visible" class="rv-actions" data-testid="verification-actions">
    <p v-if="notSynced" class="rv-actions__note" data-testid="verification-not-synced">
      Record belum tersinkron ke server — sinkronkan dulu sebelum bisa diverifikasi.
    </p>

    <button
      v-if="canCheck"
      type="button"
      class="rv-actions__button"
      :class="isChecked ? 'rv-actions__button--undo' : 'rv-actions__button--approve'"
      :disabled="busy !== null || notSynced"
      data-testid="toggle-checked-button"
      @click="toggle('checked')"
    >
      {{ isChecked ? 'Batalkan tanda diperiksa' : 'Tandai sudah diperiksa' }}
    </button>

    <button
      v-if="canAcknowledge"
      type="button"
      class="rv-actions__button"
      :class="isAcknowledged ? 'rv-actions__button--undo' : 'rv-actions__button--approve'"
      :disabled="busy !== null || notSynced"
      data-testid="toggle-acknowledged-button"
      @click="toggle('acknowledged')"
    >
      {{ isAcknowledged ? 'Batalkan tanda dikonfirmasi' : 'Tandai sudah dikonfirmasi' }}
    </button>

    <p
      v-if="message"
      class="rv-actions__message"
      :class="{ 'rv-actions__message--error': messageIsError }"
      data-testid="verification-message"
    >
      {{ message }}
    </p>
  </section>
</template>

<style scoped>
.rv-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 16px 0;
}

.rv-actions__button {
  min-height: 44px;
  padding: 10px 16px;
  border-radius: 8px;
  border: 1px solid transparent;
  font-family: inherit;
  font-size: 14px;
  font-weight: 600;
}

.rv-actions__button--approve {
  background: #249360;
  border-color: #249360;
  color: #fff;
}

.rv-actions__button--undo {
  background: #fff;
  border-color: #e3e3e3;
  color: #1f2937;
}

.rv-actions__button:disabled {
  opacity: 0.5;
}

.rv-actions__note,
.rv-actions__message {
  margin: 0;
  font-size: 12px;
  color: #6b7280;
}

.rv-actions__message--error {
  color: #dc2626;
}
</style>
