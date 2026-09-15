<script setup lang="ts">
/**
 * FormSterilizerView — screen-122--form-sterilizer /
 * usecase-122--form-sterilizer (mounted at
 * /stations/sterilizer/form/:id, meta.public = false). Actors:
 * operator, supervisor, mill_management, admin (Checked By interactive
 * only for supervisor, Acknowledged By interactive only for
 * mill_management).
 *
 * Mobile-only screen — no backend API endpoint; all data comes from the
 * local (offline) `sterilizer_record` + `sterilizer_detail` tables via
 * sterilizerRecordRepo.ts.
 *
 * Mirrors FormCpoDispatchView.vue's structural pattern (breadcrumb +
 * hamburger header, sectioned form body, dirty-tracking Back confirm,
 * Pause/Clear/Simpan footer actions) — EVENT-LOG pattern, not a
 * grid/N-column station: "Log Siklus Sterilisasi" is a free table of rows
 * added manually via "Tambah baris" (unbounded, no per-row time-slot
 * uniqueness constraint).
 *
 * `duration_minutes` is NEVER a user-editable input — it is
 * computed/displayed client-side (read-only, derived from close/open door
 * time) for immediate UX feedback, but the server remains the source of
 * truth on save (SterilizerRecordService always recomputes it).
 * `checked_by_spv` is a plain boolean checkbox per row — NOT a Supervisor
 * name reference (different from the header's Checked By).
 *
 * 'Simpan': validates the required header field (Sterilizer ID) and
 * at least one detail row with a close_door_time filled in, re-enforced
 * at the repo level (SterilizerDetailRequiredError). On success: repo
 * saveDraft() (status='saved'), navigate to `monitor-sterilizer`.
 * 'Pause': no validation, persists as-is (status='draft_paused'). 'Clear':
 * ConfirmDialog then repo deleteDraft(). 'Back' with unsaved changes shows
 * ConfirmDialog before leaving.
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import sterilizerRecordRepo, {
  computeDurationMinutes,
  SterilizerDetailRequiredError,
  type SterilizerDetailFormRow,
  type SterilizerDetailRow,
  type SterilizerHeaderFormData,
  type SterilizerRecord,
} from '@/services/sterilizerRecordRepo'
import FormField from '@/components/FormField.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { syncAfterSave } from '@/services/writeThroughSync'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()
const aiAssistantStore = useAiAssistantStore()

const recordId = String(route.params.id ?? '')

const REQUIRED_FIELDS: (keyof SterilizerHeaderFormData)[] = ['sterilizer_id']
const REQUIRED_FIELD_LABELS: Record<string, string> = {
  sterilizer_id: 'Sterilizer ID',
}
const DIRTY_CHECK_FIELDS: (keyof SterilizerHeaderFormData)[] = [
  'sterilizer_id',
  'note',
  'checked_by',
  'acknowledged_by',
]

function emptyFormData(): SterilizerHeaderFormData {
  return {
    sterilizer_id: '',
    date: '',
    note: '',
    checked_by: '',
    acknowledged_by: '',
  }
}

function emptyDetailRow(): SterilizerDetailFormRow {
  return {
    sterilizer_no: null,
    close_door_time: null,
    peak_1_time: null,
    exhaust_1_time: null,
    peak_2_time: null,
    exhaust_2_time: null,
    peak_3_time: null,
    exhaust_3_time: null,
    open_door_time: null,
    duration_minutes: null,
    number_of_cages: null,
    cages_status: null,
    checked_by_spv: false,
    remarks: null,
  }
}

const form = reactive<SterilizerHeaderFormData>(emptyFormData())
const errors = reactive<Partial<Record<keyof SterilizerHeaderFormData, string>>>({})
const detailRows = ref<SterilizerDetailFormRow[]>([])
const pendingDeletionIds = ref<string[]>([])

const loading = ref(true)
const saving = ref(false)
const pausing = ref(false)
const clearing = ref(false)
const notFound = ref(false)
const loadErrorMessage = ref<string | null>(null)
const actionErrorMessage = ref<string | null>(null)
const detailRowsError = ref<string | null>(null)
const backDialogOpen = ref(false)
const clearDialogOpen = ref(false)

const actionInProgress = computed(() => saving.value || pausing.value || clearing.value)

const isSupervisor = computed(() => authStore.currentUser?.role === 'supervisor')
const isMillManagement = computed(() => authStore.currentUser?.role === 'mill_management')

function todayLocalDateString(): string {
  const now = new Date()
  const yyyy = now.getFullYear()
  const mm = String(now.getMonth() + 1).padStart(2, '0')
  const dd = String(now.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

let loadedSnapshot = dirtySnapshot()

function dirtySnapshot(): string {
  const header: Partial<Record<keyof SterilizerHeaderFormData, string>> = {}

  for (const key of DIRTY_CHECK_FIELDS) {
    header[key] = form[key]
  }

  return JSON.stringify({
    header,
    rows: detailRows.value.map((row) => ({ ...row, id: row.id ?? null })),
    pendingDeletions: [...pendingDeletionIds.value],
  })
}

const isDirty = computed(() => dirtySnapshot() !== loadedSnapshot)

function populateForm(record: SterilizerRecord): void {
  form.sterilizer_id = record.sterilizer_id ?? ''
  form.date = record.date && record.date.trim() !== '' ? record.date : todayLocalDateString()
  form.note = record.note ?? ''
  form.checked_by = record.checked_by ?? ''
  form.acknowledged_by = record.acknowledged_by ?? ''
}

function populateDetailRows(rows: SterilizerDetailRow[]): void {
  detailRows.value = rows.map((row) => ({
    id: row.id,
    sterilizer_no: row.sterilizer_no,
    close_door_time: row.close_door_time,
    peak_1_time: row.peak_1_time,
    exhaust_1_time: row.exhaust_1_time,
    peak_2_time: row.peak_2_time,
    exhaust_2_time: row.exhaust_2_time,
    peak_3_time: row.peak_3_time,
    exhaust_3_time: row.exhaust_3_time,
    open_door_time: row.open_door_time,
    duration_minutes: row.duration_minutes,
    number_of_cages: row.number_of_cages,
    cages_status: row.cages_status,
    checked_by_spv: row.checked_by_spv,
    remarks: row.remarks,
  }))
}

async function loadDraft(): Promise<void> {
  loading.value = true
  loadErrorMessage.value = null
  notFound.value = false

  if (!recordId) {
    loading.value = false
    notFound.value = true
    return
  }

  try {
    const draft = await sterilizerRecordRepo.getDraftWithDetails(recordId)

    if (!draft) {
      notFound.value = true
      return
    }

    populateForm(draft.record)
    populateDetailRows(draft.details)
    pendingDeletionIds.value = []
    loadedSnapshot = dirtySnapshot()
  } catch (err) {
    loadErrorMessage.value = err instanceof Error ? err.message : 'Gagal memuat draft sterilizer.'
  } finally {
    loading.value = false
  }
}

onMounted(loadDraft)

function addDetailRow(): void {
  detailRows.value.push(emptyDetailRow())
}

function removeDetailRow(index: number): void {
  const row = detailRows.value[index]

  if (row?.id) {
    pendingDeletionIds.value.push(row.id)
  }

  detailRows.value.splice(index, 1)
}

function rowDurationMinutes(row: SterilizerDetailFormRow): number | null {
  return computeDurationMinutes(row.close_door_time, row.open_door_time)
}

function validateHeader(): boolean {
  Object.keys(errors).forEach((key) => delete errors[key as keyof SterilizerHeaderFormData])
  let valid = true

  for (const field of REQUIRED_FIELDS) {
    const value = form[field]
    if (value === null || value === undefined || String(value).trim() === '') {
      errors[field] = `${REQUIRED_FIELD_LABELS[field]} wajib diisi.`
      valid = false
    }
  }

  return valid
}

function buildDetailPayload(): SterilizerDetailFormRow[] {
  return detailRows.value.map((row) => ({
    ...row,
    duration_minutes: rowDurationMinutes(row),
  }))
}

async function onSimpan(): Promise<void> {
  actionErrorMessage.value = null
  detailRowsError.value = null

  const headerValid = validateHeader()
  const hasValidRow = detailRows.value.some((row) => row.close_door_time !== null && row.close_door_time !== '')

  if (!hasValidRow) {
    detailRowsError.value = 'Minimal satu baris log Sterilizer (Close Door Time wajib) harus diisi.'
  }

  if (!headerValid || !hasValidRow) {
    return
  }

  saving.value = true

  try {
    await sterilizerRecordRepo.saveDraft(
      recordId,
      { ...form },
      buildDetailPayload(),
      pendingDeletionIds.value,
      authStore.currentUser?.role ?? null,
    )
    // Write-through saving (2026-09-14): push straight to the server
    // when this mill has it enabled. Silent by design — the record is
    // already saved locally, so a failure here just means it waits for
    // the next manual sync.
    await syncAfterSave('sterilizer_record', recordId)
    router.push({ name: 'monitor-sterilizer' })
  } catch (err) {
    if (err instanceof SterilizerDetailRequiredError) {
      detailRowsError.value = err.message
    } else {
      actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menyimpan data sterilizer.'
    }
  } finally {
    saving.value = false
  }
}

async function onPause(): Promise<void> {
  actionErrorMessage.value = null
  pausing.value = true

  try {
    await sterilizerRecordRepo.pauseDraftWithFormData(
      recordId,
      { ...form },
      buildDetailPayload(),
      pendingDeletionIds.value,
      authStore.currentUser?.role ?? null,
    )
    router.push({ name: 'monitor-sterilizer' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menyimpan progres sterilizer.'
  } finally {
    pausing.value = false
  }
}

function onClearClick(): void {
  clearDialogOpen.value = true
}

async function onClearConfirm(): Promise<void> {
  clearDialogOpen.value = false
  clearing.value = true

  try {
    await sterilizerRecordRepo.deleteDraft(recordId)
    router.push({ name: 'monitor-sterilizer' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menghapus draft sterilizer.'
  } finally {
    clearing.value = false
  }
}

function onClearCancel(): void {
  clearDialogOpen.value = false
}

function onBackClick(): void {
  if (isDirty.value) {
    backDialogOpen.value = true
    return
  }

  router.push({ name: 'monitor-sterilizer' })
}

function onBackConfirm(): void {
  backDialogOpen.value = false
  router.push({ name: 'monitor-sterilizer' })
}

function onBackCancel(): void {
  backDialogOpen.value = false
}

const isNavMenuOpen = ref(false)

function toggleNavMenu() {
  isNavMenuOpen.value = !isNavMenuOpen.value
}

function closeNavMenu() {
  isNavMenuOpen.value = false
}

function openAiAssistant() {
  closeNavMenu()
  aiAssistantStore.open()
}

function goToChangePassword() {
  closeNavMenu()
  router.push({ name: 'change-password' })
}

async function onLogout(): Promise<void> {
  closeNavMenu()
  await authStore.logout()
  router.push({ name: 'login' })
}

function goToHome() {
  router.push({ name: 'home' })
}

function goToStationList() {
  router.push({ name: 'station-list' })
}

function goToMonitor() {
  router.push({ name: 'monitor-sterilizer' })
}
</script>

<template>
  <main class="form-ster-view">
    <header class="app-header">
      <div class="app-header-brand">
        <span class="brand-name">Mills Smart Log</span>
      </div>
      <button
        type="button"
        class="hamburger-button"
        data-testid="hamburger-button"
        @click="toggleNavMenu"
      >
        &#9776;
      </button>
      <div v-if="isNavMenuOpen" class="nav-menu" data-testid="nav-menu">
        <button type="button" class="nav-menu-item" @click="goToChangePassword">Ganti Password</button>
        <button type="button" class="nav-menu-item" @click="floatingClockStore.toggle()">{{ floatingClockStore.enabled ? 'Nonaktifkan Jam Mengambang' : 'Aktifkan Jam Mengambang' }}</button>
        <button type="button" class="nav-menu-item" @click="aiAssistantStore.toggleBubble()">{{ aiAssistantStore.bubbleEnabled ? 'Nonaktifkan Bubble Chat AI' : 'Aktifkan Bubble Chat AI' }}</button>
        <button type="button" class="nav-menu-item" @click="openAiAssistant">Bantuan AI</button>
        <button type="button" class="nav-menu-item" data-testid="nav-menu-logout" @click="onLogout">Logout</button>
      </div>
    </header>

    <div class="form-ster-header">
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <button type="button" class="breadcrumb-link" @click="goToHome">Home</button>
        <span aria-hidden="true">/</span>
        <button type="button" class="breadcrumb-link" @click="goToStationList">Production Process Activity</button>
        <span aria-hidden="true">/</span>
        <button type="button" class="breadcrumb-link" @click="goToMonitor">Sterilizer</button>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Form</span>
      </nav>
      <h1 class="screen-title">Form Sterilizer</h1>
    </div>

    <p v-if="loading" class="status-text">Memuat draft sterilizer…</p>
    <p v-else-if="notFound" class="status-text status-text--error" role="alert" data-testid="record-not-found">Draft tidak ditemukan.</p>
    <p v-else-if="loadErrorMessage" class="status-text status-text--error" role="alert">{{ loadErrorMessage }}</p>

    <form v-else class="form-body" novalidate @submit.prevent>
      <p v-if="actionErrorMessage" class="status-text status-text--error" role="alert" data-testid="action-error">{{ actionErrorMessage }}</p>

      <section class="form-section">
        <h2 class="section-title">Identitas Sterilizer</h2>
        <FormField
          id="sterilizer_id"
          v-model="form.sterilizer_id"
          label="Sterilizer ID"
          required
          :error="errors.sterilizer_id"
        />
        <FormField id="date" v-model="form.date" label="Tanggal" type="date" disabled />
      </section>

      <section class="form-section">
        <h2 class="section-title">Verifikasi</h2>
        <p class="readonly-field">Inputted By: {{ authStore.currentUser?.name ?? '-' }}</p>
        <label class="checkbox-field">
          <input
            type="checkbox"
            :checked="!!form.checked_by"
            :disabled="!isSupervisor"
            data-testid="checked-by-checkbox"
            @change="form.checked_by = ($event.target as HTMLInputElement).checked ? (authStore.currentUser?.id ?? '') : ''"
          />
          Checked By (Supervisor)
        </label>
        <label class="checkbox-field">
          <input
            type="checkbox"
            :checked="!!form.acknowledged_by"
            :disabled="!isMillManagement"
            data-testid="acknowledged-by-checkbox"
            @change="form.acknowledged_by = ($event.target as HTMLInputElement).checked ? (authStore.currentUser?.id ?? '') : ''"
          />
          Acknowledged By (Mill Management)
        </label>
        <FormField v-model="form.note" label="Note" />
      </section>

      <section class="form-section">
        <h2 class="section-title">Log Siklus Sterilisasi</h2>
        <p v-if="detailRowsError" class="status-text status-text--error" role="alert" data-testid="detail-rows-error">{{ detailRowsError }}</p>

        <div class="detail-table-wrap" data-testid="sterilizer-detail-log">
          <table class="detail-table">
            <thead>
              <tr>
                <th>Sterilizer No</th>
                <th>Close Door Time</th>
                <th>Peak 1 Time</th>
                <th>Exhaust 1 Time</th>
                <th>Peak 2 Time</th>
                <th>Exhaust 2 Time</th>
                <th>Peak 3 Time</th>
                <th>Exhaust 3 Time</th>
                <th>Open Door Time</th>
                <th>Duration (Minutes)</th>
                <th>Number of Cages</th>
                <th>Cages Status</th>
                <th>Checked by SPV</th>
                <th>Remarks</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in detailRows" :key="row.id ?? `new-${index}`" :data-testid="`detail-row-${index}`">
                <td><input type="text" v-model="row.sterilizer_no" /></td>
                <td><input type="time" v-model="row.close_door_time" :data-testid="`detail-close-door-time-${index}`" /></td>
                <td><input type="time" v-model="row.peak_1_time" /></td>
                <td><input type="time" v-model="row.exhaust_1_time" /></td>
                <td><input type="time" v-model="row.peak_2_time" /></td>
                <td><input type="time" v-model="row.exhaust_2_time" /></td>
                <td><input type="time" v-model="row.peak_3_time" /></td>
                <td><input type="time" v-model="row.exhaust_3_time" /></td>
                <td><input type="time" v-model="row.open_door_time" :data-testid="`detail-open-door-time-${index}`" /></td>
                <td><input type="text" :value="rowDurationMinutes(row) ?? '-'" disabled :data-testid="`detail-duration-minutes-${index}`" /></td>
                <td><input type="number" v-model.number="row.number_of_cages" /></td>
                <td><input type="text" v-model="row.cages_status" /></td>
                <td><input type="checkbox" v-model="row.checked_by_spv" :data-testid="`detail-checked-by-spv-${index}`" /></td>
                <td><input type="text" v-model="row.remarks" /></td>
                <td>
                  <button type="button" class="action-button action-button--secondary" :data-testid="`remove-row-button-${index}`" @click="removeDetailRow(index)">
                    Hapus
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <button type="button" class="action-button action-button--secondary" data-testid="add-row-button" @click="addDetailRow">
          + Tambah Baris
        </button>
      </section>

      <footer class="action-footer">
        <div class="action-row">
          <button type="button" class="action-button action-button--secondary" data-testid="back-button" :disabled="actionInProgress" @click="onBackClick">
            Back
          </button>
          <button type="button" class="action-button action-button--secondary" data-testid="pause-button" :disabled="actionInProgress" @click="onPause">
            Pause
          </button>
          <button type="button" class="action-button action-button--secondary" data-testid="clear-button" :disabled="actionInProgress" @click="onClearClick">
            Clear
          </button>
        </div>
        <button type="button" class="action-button action-button--primary" data-testid="save-button" :disabled="actionInProgress" @click="onSimpan">
          Simpan
        </button>
      </footer>
    </form>

    <ConfirmDialog
      :open="backDialogOpen"
      message="Ada perubahan yang belum disimpan. Yakin ingin kembali?"
      confirm-label="Ya, Kembali"
      @confirm="onBackConfirm"
      @cancel="onBackCancel"
    />

    <ConfirmDialog
      :open="clearDialogOpen"
      message="Draft ini akan dihapus permanen. Yakin ingin menghapus?"
      confirm-label="Ya, Hapus"
      @confirm="onClearConfirm"
      @cancel="onClearCancel"
    />
  </main>
</template>

<style scoped>
.form-ster-view { min-height: 100vh; display: flex; flex-direction: column; gap: 16px; padding: 0 16px 20px; background: #ffffff; font-family: 'Inter', sans-serif; box-sizing: border-box; }
.app-header { position: relative; display: flex; align-items: center; justify-content: space-between; min-height: 64px; margin: 0 -16px; padding: 0 16px; background: #ffffff; }
.brand-name { font-size: 16px; font-weight: 700; color: #1f2937; }
.hamburger-button { border: none; background: transparent; font-size: 20px; cursor: pointer; }
.nav-menu { position: absolute; top: 64px; right: 0; z-index: 10; display: flex; flex-direction: column; min-width: 180px; padding: 6px; border-radius: 12px; background: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.06); border: 1px solid #f7f7f7; }
.nav-menu-item { min-height: 44px; padding: 0 12px; border: none; border-radius: 8px; background: transparent; font-size: 14px; font-weight: 500; text-align: left; cursor: pointer; }
.form-ster-header { display: flex; flex-direction: column; gap: 6px; }
.breadcrumb { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 12px; color: #6b7280; }
.breadcrumb-link { border: none; background: transparent; color: #6b7280; font-size: 12px; cursor: pointer; padding: 0; }
.screen-title { margin: 0; font-size: 20px; font-weight: 600; color: #1f2937; }
.status-text { font-size: 14px; color: #6b7280; }
.status-text--error { color: #dc2626; }
.form-body { display: flex; flex-direction: column; gap: 20px; }
.form-section { display: flex; flex-direction: column; gap: 12px; padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; }
.section-title { margin: 0; font-size: 16px; font-weight: 700; color: #1f2937; }
.readonly-field { margin: 0; font-size: 14px; color: #6b7280; }
.checkbox-field { display: flex; align-items: center; gap: 8px; font-size: 14px; }
.detail-table-wrap { width: 100%; overflow-x: auto; }
.detail-table { border-collapse: collapse; min-width: 1700px; }
.detail-table th { text-align: left; font-size: 12px; font-weight: 600; color: #6b7280; padding: 6px 8px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
.detail-table td { padding: 6px 8px; }
.detail-table input, .detail-table select { min-width: 100px; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; font-family: inherit; }
.detail-table input:disabled { background: #f3f4f6; color: #6b7280; }
.action-footer { display: flex; flex-direction: column; gap: 12px; margin-top: 8px; }
.action-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.action-button { min-height: 44px; border-radius: 8px; font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer; box-sizing: border-box; }
.action-button--secondary { border: 1px solid #e5e7eb; background: #ffffff; color: #1f2937; }
.action-button--primary { border: none; background: #249360; color: #ffffff; font-size: 16px; }
.action-button:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
