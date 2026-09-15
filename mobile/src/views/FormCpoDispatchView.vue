<script setup lang="ts">
/**
 * FormCpoDispatchView — screen-074--form-cpo-dispatch /
 * usecase-080--form-cpo-dispatch (mounted at
 * /stations/cpo-dispatch/form/:id, meta.public = false). Actors:
 * operator, supervisor, mill_management, admin (Checked By interactive
 * only for supervisor, Acknowledged By interactive only for
 * mill_management).
 *
 * Mobile-only screen — no backend API endpoint; all data comes from the
 * local (offline) `cpo_dispatch_record` + `cpo_dispatch_detail`
 * tables via cpoDispatchRecordRepo.ts.
 *
 * Mirrors FormKernelDispatchView.vue's structural pattern (breadcrumb +
 * hamburger header, sectioned form body, dirty-tracking Back confirm,
 * Pause/Clear/Simpan footer actions) — EVENT-LOG pattern, not a
 * grid/N-column station: "Log Kejadian" is a free table of rows added
 * manually via "Tambah baris" (unbounded, no per-row time-slot uniqueness
 * constraint, no shared checkbox-column concept). Unlike Kernel Dispatch,
 * there is no Remarks/Gate Status select — CPO Dispatch has Time In/Time
 * Out (two separate time inputs) and DOBI instead.
 *
 * 'Simpan': validates the required header field (CPO Dispatch ID) and
 * at least one detail row with an event_date filled in, re-enforced at the
 * repo level (CpoDispatchDetailRequiredError). On success: repo
 * saveDraft() (status='saved'), navigate to `monitor-cpo-dispatch`.
 * 'Pause': no validation, persists as-is (status='draft_paused'). 'Clear':
 * ConfirmDialog then repo deleteDraft(). 'Back' with unsaved changes shows
 * ConfirmDialog before leaving.
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import cpoDispatchRecordRepo, {
  CpoDispatchDetailRequiredError,
  type CpoDispatchDetailFormRow,
  type CpoDispatchDetailRow,
  type CpoDispatchHeaderFormData,
  type CpoDispatchRecord,
} from '@/services/cpoDispatchRecordRepo'
import FormField from '@/components/FormField.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { syncAfterSave } from '@/services/writeThroughSync'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()
const aiAssistantStore = useAiAssistantStore()

const recordId = String(route.params.id ?? '')

const REQUIRED_FIELDS: (keyof CpoDispatchHeaderFormData)[] = ['cpo_dispatch_id']
const REQUIRED_FIELD_LABELS: Record<string, string> = {
  cpo_dispatch_id: 'CPO Dispatch ID',
}
const DIRTY_CHECK_FIELDS: (keyof CpoDispatchHeaderFormData)[] = [
  'cpo_dispatch_id',
  'note',
  'checked_by',
  'acknowledged_by',
]

function emptyFormData(): CpoDispatchHeaderFormData {
  return {
    cpo_dispatch_id: '',
    date: '',
    note: '',
    checked_by: '',
    acknowledged_by: '',
  }
}

function emptyDetailRow(): CpoDispatchDetailFormRow {
  return {
    event_date: null,
    shift: null,
    time_in: null,
    time_out: null,
    waybill_number: null,
    tanker_plate_no: null,
    transport_company: null,
    driver_name: null,
    storage_tank_source: null,
    seal_no_top: null,
    seal_no_bottom: null,
    gross_weight_mt: null,
    tare_weight_mt: null,
    net_weight_mt: null,
    ffa_percent: null,
    moisture_percent: null,
    impurities_percent: null,
    dobi: null,
    destination_buyer: null,
    weighbridge_operator: null,
    findings: null,
  }
}

const form = reactive<CpoDispatchHeaderFormData>(emptyFormData())
const errors = reactive<Partial<Record<keyof CpoDispatchHeaderFormData, string>>>({})
const detailRows = ref<CpoDispatchDetailFormRow[]>([])
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
  const header: Partial<Record<keyof CpoDispatchHeaderFormData, string>> = {}

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

function populateForm(record: CpoDispatchRecord): void {
  form.cpo_dispatch_id = record.cpo_dispatch_id ?? ''
  form.date = record.date && record.date.trim() !== '' ? record.date : todayLocalDateString()
  form.note = record.note ?? ''
  form.checked_by = record.checked_by ?? ''
  form.acknowledged_by = record.acknowledged_by ?? ''
}

function populateDetailRows(rows: CpoDispatchDetailRow[]): void {
  detailRows.value = rows.map((row) => ({
    id: row.id,
    event_date: row.event_date,
    shift: row.shift,
    time_in: row.time_in,
    time_out: row.time_out,
    waybill_number: row.waybill_number,
    tanker_plate_no: row.tanker_plate_no,
    transport_company: row.transport_company,
    driver_name: row.driver_name,
    storage_tank_source: row.storage_tank_source,
    seal_no_top: row.seal_no_top,
    seal_no_bottom: row.seal_no_bottom,
    gross_weight_mt: row.gross_weight_mt,
    tare_weight_mt: row.tare_weight_mt,
    net_weight_mt: row.net_weight_mt,
    ffa_percent: row.ffa_percent,
    moisture_percent: row.moisture_percent,
    impurities_percent: row.impurities_percent,
    dobi: row.dobi,
    destination_buyer: row.destination_buyer,
    weighbridge_operator: row.weighbridge_operator,
    findings: row.findings,
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
    const draft = await cpoDispatchRecordRepo.getDraftWithDetails(recordId)

    if (!draft) {
      notFound.value = true
      return
    }

    populateForm(draft.record)
    populateDetailRows(draft.details)
    pendingDeletionIds.value = []
    loadedSnapshot = dirtySnapshot()
  } catch (err) {
    loadErrorMessage.value = err instanceof Error ? err.message : 'Gagal memuat draft cpo dispatch.'
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

function rowNetWeight(row: CpoDispatchDetailFormRow): number | null {
  if (row.gross_weight_mt === null || row.tare_weight_mt === null) {
    return null
  }

  return row.gross_weight_mt - row.tare_weight_mt
}

function validateHeader(): boolean {
  Object.keys(errors).forEach((key) => delete errors[key as keyof CpoDispatchHeaderFormData])
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

function buildDetailPayload(): CpoDispatchDetailFormRow[] {
  return detailRows.value.map((row) => ({
    ...row,
    net_weight_mt: rowNetWeight(row),
  }))
}

async function onSimpan(): Promise<void> {
  actionErrorMessage.value = null
  detailRowsError.value = null

  const headerValid = validateHeader()
  const hasValidRow = detailRows.value.some((row) => row.event_date !== null && row.event_date !== '')

  if (!hasValidRow) {
    detailRowsError.value = 'Minimal satu baris log CPO Dispatch (Tanggal Kejadian wajib) harus diisi.'
  }

  if (!headerValid || !hasValidRow) {
    return
  }

  saving.value = true

  try {
    await cpoDispatchRecordRepo.saveDraft(
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
    await syncAfterSave('cpo_dispatch_record', recordId)
    router.push({ name: 'monitor-cpo-dispatch' })
  } catch (err) {
    if (err instanceof CpoDispatchDetailRequiredError) {
      detailRowsError.value = err.message
    } else {
      actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menyimpan data cpo dispatch.'
    }
  } finally {
    saving.value = false
  }
}

async function onPause(): Promise<void> {
  actionErrorMessage.value = null
  pausing.value = true

  try {
    await cpoDispatchRecordRepo.pauseDraftWithFormData(
      recordId,
      { ...form },
      buildDetailPayload(),
      pendingDeletionIds.value,
      authStore.currentUser?.role ?? null,
    )
    router.push({ name: 'monitor-cpo-dispatch' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menyimpan progres cpo dispatch.'
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
    await cpoDispatchRecordRepo.deleteDraft(recordId)
    router.push({ name: 'monitor-cpo-dispatch' })
  } catch (err) {
    actionErrorMessage.value = err instanceof Error ? err.message : 'Gagal menghapus draft cpo dispatch.'
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

  router.push({ name: 'monitor-cpo-dispatch' })
}

function onBackConfirm(): void {
  backDialogOpen.value = false
  router.push({ name: 'monitor-cpo-dispatch' })
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
  router.push({ name: 'monitor-cpo-dispatch' })
}
</script>

<template>
  <main class="form-cd-view">
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

    <div class="form-cd-header">
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <button type="button" class="breadcrumb-link" @click="goToHome">Home</button>
        <span aria-hidden="true">/</span>
        <button type="button" class="breadcrumb-link" @click="goToStationList">Production Process Activity</button>
        <span aria-hidden="true">/</span>
        <button type="button" class="breadcrumb-link" @click="goToMonitor">CPO Dispatch</button>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Form</span>
      </nav>
      <h1 class="screen-title">Form CPO Dispatch</h1>
    </div>

    <p v-if="loading" class="status-text">Memuat draft cpo dispatch…</p>
    <p v-else-if="notFound" class="status-text status-text--error" role="alert" data-testid="record-not-found">Draft tidak ditemukan.</p>
    <p v-else-if="loadErrorMessage" class="status-text status-text--error" role="alert">{{ loadErrorMessage }}</p>

    <form v-else class="form-body" novalidate @submit.prevent>
      <p v-if="actionErrorMessage" class="status-text status-text--error" role="alert" data-testid="action-error">{{ actionErrorMessage }}</p>

      <section class="form-section">
        <h2 class="section-title">Identitas CPO Dispatch</h2>
        <FormField
          id="cpo_dispatch_id"
          v-model="form.cpo_dispatch_id"
          label="CPO Dispatch ID"
          required
          :error="errors.cpo_dispatch_id"
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
        <h2 class="section-title">Log Kejadian CPO Dispatch</h2>
        <p v-if="detailRowsError" class="status-text status-text--error" role="alert" data-testid="detail-rows-error">{{ detailRowsError }}</p>

        <div class="detail-table-wrap" data-testid="cpo-dispatch-detail-log">
          <table class="detail-table">
            <thead>
              <tr>
                <th>Tanggal Kejadian</th>
                <th>Shift</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Waybill Number</th>
                <th>Tanker Plate No</th>
                <th>Transport Company</th>
                <th>Driver Name</th>
                <th>Storage Tank Source</th>
                <th>Seal No (Top)</th>
                <th>Seal No (Bottom)</th>
                <th>Gross (MT)</th>
                <th>Tare (MT)</th>
                <th>Net (MT)</th>
                <th>FFA (%)</th>
                <th>Moisture (%)</th>
                <th>Impurities (%)</th>
                <th>DOBI</th>
                <th>Destination/Buyer</th>
                <th>Weighbridge Operator</th>
                <th>Findings</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in detailRows" :key="row.id ?? `new-${index}`" :data-testid="`detail-row-${index}`">
                <td><input type="date" v-model="row.event_date" :data-testid="`detail-event-date-${index}`" /></td>
                <td><input type="text" v-model="row.shift" /></td>
                <td><input type="time" v-model="row.time_in" :data-testid="`detail-time-in-${index}`" /></td>
                <td><input type="time" v-model="row.time_out" :data-testid="`detail-time-out-${index}`" /></td>
                <td><input type="text" v-model="row.waybill_number" /></td>
                <td><input type="text" v-model="row.tanker_plate_no" /></td>
                <td><input type="text" v-model="row.transport_company" /></td>
                <td><input type="text" v-model="row.driver_name" /></td>
                <td><input type="text" v-model="row.storage_tank_source" /></td>
                <td><input type="text" v-model="row.seal_no_top" /></td>
                <td><input type="text" v-model="row.seal_no_bottom" /></td>
                <td><input type="number" step="0.01" v-model.number="row.gross_weight_mt" :data-testid="`detail-gross-weight-${index}`" /></td>
                <td><input type="number" step="0.01" v-model.number="row.tare_weight_mt" :data-testid="`detail-tare-weight-${index}`" /></td>
                <td><input type="text" :value="rowNetWeight(row) ?? '-'" disabled :data-testid="`detail-net-weight-${index}`" /></td>
                <td><input type="number" step="0.01" v-model.number="row.ffa_percent" /></td>
                <td><input type="number" step="0.01" v-model.number="row.moisture_percent" /></td>
                <td><input type="number" step="0.01" v-model.number="row.impurities_percent" /></td>
                <td><input type="number" step="0.01" v-model.number="row.dobi" /></td>
                <td><input type="text" v-model="row.destination_buyer" /></td>
                <td><input type="text" v-model="row.weighbridge_operator" /></td>
                <td><input type="text" v-model="row.findings" /></td>
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
.form-cd-view { min-height: 100vh; display: flex; flex-direction: column; gap: 16px; padding: 0 16px 20px; background: #ffffff; font-family: 'Inter', sans-serif; box-sizing: border-box; }
.app-header { position: relative; display: flex; align-items: center; justify-content: space-between; min-height: 64px; margin: 0 -16px; padding: 0 16px; background: #ffffff; }
.brand-name { font-size: 16px; font-weight: 700; color: #1f2937; }
.hamburger-button { border: none; background: transparent; font-size: 20px; cursor: pointer; }
.nav-menu { position: absolute; top: 64px; right: 0; z-index: 10; display: flex; flex-direction: column; min-width: 180px; padding: 6px; border-radius: 12px; background: #ffffff; box-shadow: 0 1px 2px rgba(0,0,0,0.06); border: 1px solid #f7f7f7; }
.nav-menu-item { min-height: 44px; padding: 0 12px; border: none; border-radius: 8px; background: transparent; font-size: 14px; font-weight: 500; text-align: left; cursor: pointer; }
.form-cd-header { display: flex; flex-direction: column; gap: 6px; }
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
.detail-table { border-collapse: collapse; min-width: 2000px; }
.detail-table th { text-align: left; font-size: 12px; font-weight: 600; color: #6b7280; padding: 6px 8px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
.detail-table td { padding: 6px 8px; }
.detail-table input, .detail-table select { min-width: 110px; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; font-family: inherit; }
.detail-table input:disabled { background: #f3f4f6; color: #6b7280; }
.action-footer { display: flex; flex-direction: column; gap: 12px; margin-top: 8px; }
.action-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.action-button { min-height: 44px; border-radius: 8px; font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer; box-sizing: border-box; }
.action-button--secondary { border: 1px solid #e5e7eb; background: #ffffff; color: #1f2937; }
.action-button--primary { border: none; background: #249360; color: #ffffff; font-size: 16px; }
.action-button:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
